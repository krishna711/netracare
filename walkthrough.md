# ABDM Milestone 1 & 2 Integration Walkthrough

We have integrated **ABDM (Ayushman Bharat Digital Mission) Milestone 1 (M1)** and **Milestone 2 (M2)** into NetraCare, conforming strictly to the **ABDM V3 API standard** and incorporating the **NHA Bridge Onboarding Steps**.

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

### Official NHA Milestone 2 Postman Specification Alignment (`Milestone_2_16_02_2026_6e734af067.postman_collection`)
1. **`On Discovery` (`POST https://dev.abdm.gov.in/api/hiecm/user-initiated-linking/v3/patient/care-context/on-discover`):**
   - **Payload Structure**:
     ```json
     {
       "transactionId": "<transactionId>",
       "patient": [
         {
           "referenceNumber": "P-3",
           "display": "Balkrishna Verma",
           "careContexts": [
             {
               "referenceNumber": "OPD-APP-42778",
               "display": "Ophthalmology Consultation - 11 Sep 2026 with Dr. Vineet Gour"
             }
           ],
           "hiType": "OPConsultation",
           "count": 1
         }
       ],
       "matchedBy": ["MOBILE"],
       "response": {
         "requestId": "<inbound-requestId>"
       }
     }
     ```
   - **Critical Nuances**:
     - `patient` is an **array of objects** (`[ { ... } ]`), not a single object.
     - Each patient object in the array specifies `"hiType"` (e.g. `OPConsultation`) and `"count": 1`.
     - `"matchedBy"` is placed at the **root level**.
     - The correlation object is named **`"response"`** (not `"resp"`).
     - Returns `202 Accepted` from Gateway.

2. **`Link on-init` (`POST https://dev.abdm.gov.in/api/hiecm/user-initiated-linking/v3/link/care-context/on-init`):**
   - Correlates with `"response": { "requestId": "<inbound-requestId>" }`.
   - Sends `meta.communicationMedium: "MOBILE"` and `communicationHint: "OTP"`.

3. **`Link on Confirm` (`POST https://dev.abdm.gov.in/api/hiecm/user-initiated-linking/v3/link/care-context/on-confirm`):**
   - Correlates with `"response": { "requestId": "<inbound-requestId>" }`.
   - Sends confirmed care contexts inside the `patient` array.

