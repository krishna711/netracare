# ABDM Milestone 1 (M1) Integration Walkthrough

We have completed the full integration of **ABDM (Ayushman Bharat Digital Mission) Milestone 1 (M1)** into NetraCare, conforming to the **ABDM V3 API standard** and incorporating the **NHA Bridge Onboarding Steps**.

---

## What Has Been Built

### 1. ABDM V3 Security & Authentication Layer
- **[AbdmClient.php](file:///d:/CascadeProjects/netracarenew/app/Services/Abdm/AbdmClient.php)**:
  - Connects to the ABDM Gateway (`https://dev.abdm.gov.in/api/hiecm/gateway/v3/sessions`).
  - Authenticates via Client ID & Client Secret using `grantType: client_credentials`.
  - Automatically manages token caching and proactive token refresh.
  - Automatically injects mandatory ABDM headers: `REQUEST-ID`, `TIMESTAMP`, `X-CM-ID`, and `Authorization: Bearer <token>`.
  - Implements a live `testConnection()` health check.
- **[AbdmCryptoService.php](file:///d:/CascadeProjects/netracarenew/app/Services/Abdm/AbdmCryptoService.php)**:
  - Fetches the dynamic public key certificate from ABDM.
  - Implements **RSA/ECB/OAEPWithSHA-1AndMGF1Padding** (`OPENSSL_PKCS1_OAEP_PADDING`) to encrypt Aadhaar numbers, OTPs, and mobile numbers.

---

### 2. NHA Bridge Onboarding Service (Email Steps 1, 2, 3)
- **[AbdmBridgeService.php](file:///d:/CascadeProjects/netracarenew/app/Services/Abdm/AbdmBridgeService.php)**:
  - **Step 1**: `updateBridgeUrl(string $url)` — patches your public HTTPS callback endpoint using official ABDM V3: `PATCH https://dev.abdm.gov.in/api/hiecm/gateway/v3/bridge/url`.
  - **Step 2**: `addUpdateServices()` — registers `Netrika Netralaya` as an active `HIP` in the Mock Facility Registry via `POST https://facilitysbx.abdm.gov.in/v1/bridges/MutipleHRPAddUpdateServices`.
  - **Step 3**: `getServices()` — fetches all registered bridge services from `GET https://dev.abdm.gov.in/api/hiecm/gateway/v3/bridge-services` to confirm active registration and close the onboarding ticket.

---

## Live Deployment Troubleshooting & Fixes (Applied in Commit `308b568`)

### 1. Fix for "Public key certificate not found in ABDM response"
- **Cause:** When calling ABDM certs endpoint (`/gateway/v3/certs`), the gateway returns JWKS format `{"keys":[{"x5c":["..."]}]}` rather than a plain `publicKey` string. Because the parser only checked `$json['publicKey']`, it failed to locate the certificate.
- **Fix:** Upgraded `AbdmClient::getPublicCertificate()` with a multi-layered extraction:
  1. Primary: ABHA V3 Profile certificate endpoint (`https://abhasbx.abdm.gov.in/abha/api/v3/profile/public/certificate`).
  2. Fallback 1: Gateway V3 certs (`https://dev.abdm.gov.in/api/hiecm/gateway/v3/certs`).
  3. Fallback 2: Gateway V0.5 certs (`https://dev.abdm.gov.in/gateway/v0.5/certs`) which reliably returns the X.509 RSA certificate.
  4. JWKS parser: Intelligently extracts `keys[0].x5c[0]` or `keys[0].publicKey` and formats it into valid OpenSSL PEM certificate format.

### 2. Fix for "Failed to update Bridge URL (403): API Subscription validation failed (900908)"
- **Cause:** The legacy endpoint `https://dev.abdm.gov.in/gateway/v1/bridges` is deprecated on the ABDM Sandbox. New bridge client IDs are only subscribed to the ABDM V3 Gateway (`https://dev.abdm.gov.in/api/hiecm/gateway/v3/*`), triggering WSO2 error `900908: Resource forbidden. User is NOT authorized to access the Resource`.
- **Fix:** 
  1. Updated `updateBridgeUrl()` to call the official ABDM V3 endpoint: `PATCH https://dev.abdm.gov.in/api/hiecm/gateway/v3/bridge/url` with standard V3 headers (`REQUEST-ID`, UTC ISO `TIMESTAMP`, `X-CM-ID`, and `Authorization: Bearer <token>`).
  2. Updated `addUpdateServices()` to use `POST https://facilitysbx.abdm.gov.in/v1/bridges/MutipleHRPAddUpdateServices`.
  3. Updated `getServices()` to use `GET https://dev.abdm.gov.in/api/hiecm/gateway/v3/bridge-services`.
  4. Auto-save settings in `AbdmSettings.php` prior to triggering bridge actions.

---

### 3. ABHA Identity Management (M1 Core)
- **[AbhaEnrollmentService.php](file:///d:/CascadeProjects/netracarenew/app/Services/Abdm/AbhaEnrollmentService.php)**:
  - Request Aadhaar OTP (`POST /v3/enrollment/request/otp`).
  - Verify Aadhaar OTP & generate ABHA Profile (`POST /v3/enrollment/enrol/byAadhaar`).
  - Suggest ABHA addresses & assign custom handle (`POST /v3/enrollment/enrol/abha-address`).
- **[AbhaVerifyService.php](file:///d:/CascadeProjects/netracarenew/app/Services/Abdm/AbhaVerifyService.php)**:
  - Search existing ABHA accounts by ABHA Number or Mobile (`POST /v3/profile/account/abha/search`).
  - Request login verification OTP (`POST /v3/profile/login/request/otp`).
  - Verify OTP and fetch profile (`POST /v3/profile/login/verify`).
- **[AbdmPatientAction.php](file:///d:/CascadeProjects/netracarenew/app/Filament/Resources/Patients/Actions/AbdmPatientAction.php)**:
  - Interactive modal button directly on the **Patients Table** for 1-click ABHA Creation or Verification.

---

### 4. Printable Official ABHA Card
- **[PrintController.php](file:///d:/CascadeProjects/netracarenew/app/Http/Controllers/PrintController.php)** (`printAbhaCard`):
  - Route: `/print/patient/abha-card/{id}`
  - **[abhacard.blade.php](file:///d:/CascadeProjects/netracarenew/resources/views/print/abhacard.blade.php)**:
    - Official CR80 standard card design with Indian tricolor banner, emblem, photo, ABHA number, ABHA address, QR code, and Print button.

---

### 5. Scan & Share (Counter Fast-Track Registration)
- **[ScanAndShareService.php](file:///d:/CascadeProjects/netracarenew/app/Services/Abdm/ScanAndShareService.php)**:
  - Generates hospital counter QR code payload for reception check-in.
  - Automatically receives patient demographic tokens pushed by ABDM Gateway at `POST /api/v3/hip/patient/share`.
  - Sends immediate acknowledgement with token number to `POST /api/hiecm/patient-share/v3/on-share`.
- **[AbdmWebhookController.php](file:///d:/CascadeProjects/netracarenew/app/Http/Controllers/AbdmWebhookController.php)**:
  - Endpoint registered in [routes/api.php](file:///d:/CascadeProjects/netracarenew/routes/api.php).
- **[AbdmScanSharePage.php](file:///d:/CascadeProjects/netracarenew/app/Filament/Pages/AbdmScanSharePage.php)**:
  - Live reception counter dashboard in Filament displaying the active Counter QR code.
  - Live check-in queue showing arriving patients with **"1-Click Register & Book OPD"** button.

---

### 6. Admin Settings Panel
- **[AbdmSettings.php](file:///d:/CascadeProjects/netracarenew/app/Filament/Pages/AbdmSettings.php)**:
  - Accessible via Filament Sidebar under **ABDM / Ayushman Bharat** -> **ABDM Settings**.
  - Configure Client ID, Client Secret, Facility ID, CM-ID, Environment, and Public URL.
  - **Header Actions**:
    1. **"Test Gateway Connection"**: Tests token generation and public cert fetch with live latency display.
    2. **"1. Update Bridge URL"**: Runs Step 1 from NHA email.
    3. **"2. Register HIP Service"**: Runs Step 2 from NHA email.
    4. **"3. View Registered Services"**: Runs Step 3 from NHA email and displays response data.

---

## How to Test and Use

### Step 1: Add your Client ID & Secret
1. Open your browser and navigate to the admin panel: `http://localhost:8000/admin` (or your local domain).
2. Go to **ABDM / Ayushman Bharat** -> **ABDM Settings**.
3. Enter your **Client ID / Bridge ID** and **Client Secret**.
4. Click **Save ABDM Settings**.
5. Click the **"Test Gateway Connection"** button at the top right:
   - It will ping ABDM Gateway, generate a session token, retrieve the RSA certificate, and display a green success badge with latency stats!

### Step 2: Fulfill NHA Onboarding Steps
Right from the **ABDM Settings** page header:
1. Enter your public HTTPS URL (e.g. your ngrok or domain) in **Public Callback URL** and click **"1. Update Bridge URL"**.
2. Click **"2. Register HIP Service"** to register your facility in the mock registry.
3. Click **"3. View Registered Services"** to confirm your service is active.
4. Reply to `integration.support@nha.gov.in` stating that Bridge URL and HIP service have been added to close your ticket!

### Step 3: Test ABHA Creation / Verification on a Patient
1. Go to **Patients** in the admin sidebar.
2. Note the new **ABHA ID** column (displays badge `Not Linked` or verified ABHA number).
3. Click the **ABHA** button on any patient row:
   - **To Create ABHA**: Select "Create New ABHA", enter 12-digit Aadhaar, click "Send OTP", enter the OTP received on mobile, and click "Complete & Save".
   - Once linked, click **"Print ABHA Card"** to see and print the patient's card!

### Step 4: Test Fast-Track Scan & Share
1. In the sidebar, go to **ABDM / Ayushman Bharat** -> **Scan & Share (Counter)**.
2. The reception counter QR code is displayed on the left.
3. When patients scan this code with their ABHA app, their details instantly appear in the **Incoming Patient Check-In Queue**.
4. Click **"Register & Book OPD"** to create their patient record and schedule their consultation in 1 click!
