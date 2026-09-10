<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ABHA Card - {{ $patient->name }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f1f5f9;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .no-print {
            margin-bottom: 20px;
        }

        .btn {
            background-color: #0284c7;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn:hover {
            background-color: #0369a1;
        }

        /* Standard ABHA Card Dimensions (CR80 ratio ~ 85.6mm x 54mm or 500px x 315px) */
        .abha-card {
            width: 540px;
            height: 330px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            position: relative;
            background-repeat: no-repeat;
            background-position: center;
        }

        /* Indian Flag Header Accent */
        .card-header-bar {
            height: 6px;
            background: linear-gradient(90deg, #ff9933 0%, #ff9933 33.3%, #ffffff 33.3%, #ffffff 66.6%, #138808 66.6%, #138808 100%);
        }

        .card-top {
            padding: 12px 18px 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
        }

        .govt-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .emblem {
            font-size: 24px;
            font-weight: bold;
            color: #1e293b;
        }

        .title-group h2 {
            margin: 0;
            font-size: 14px;
            color: #0f172a;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .title-group p {
            margin: 0;
            font-size: 10px;
            color: #64748b;
            font-weight: 600;
        }

        .abdm-badge {
            background: #0284c7;
            color: white;
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 6px;
            letter-spacing: 0.5px;
        }

        .card-body {
            display: flex;
            padding: 14px 18px;
            gap: 18px;
            flex: 1;
        }

        .photo-container {
            width: 100px;
            height: 120px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8fafc;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .photo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .details-col {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .patient-name {
            font-size: 17px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 6px 0;
        }

        .info-row {
            display: flex;
            font-size: 12px;
            margin-bottom: 4px;
            color: #334155;
        }

        .info-label {
            width: 90px;
            color: #64748b;
            font-weight: 600;
        }

        .info-val {
            font-weight: 700;
            color: #1e293b;
        }

        .qr-col {
            width: 90px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .qr-box {
            width: 85px;
            height: 85px;
            border: 1px solid #e2e8f0;
            padding: 2px;
            background: white;
            border-radius: 6px;
        }

        .qr-box img {
            width: 100%;
            height: 100%;
        }

        .card-footer {
            background-color: #0f172a;
            color: white;
            padding: 10px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .abha-number-box {
            display: flex;
            flex-direction: column;
        }

        .abha-number-label {
            font-size: 9px;
            color: #94a3b8;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .abha-number-value {
            font-size: 18px;
            font-weight: 900;
            letter-spacing: 2px;
            color: #38bdf8;
            font-family: 'Courier New', Courier, monospace;
        }

        .abha-address-box {
            text-align: right;
        }

        .abha-address-value {
            font-size: 12px;
            font-weight: 700;
            color: #f8fafc;
        }

        @media print {
            body {
                background: none;
                padding: 0;
            }
            .no-print {
                display: none;
            }
            .abha-card {
                box-shadow: none;
                border: 1px solid #333;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn" onclick="window.print()">🖨️ Print ABHA Card</button>
    </div>

    <div class="abha-card">
        <div class="card-header-bar"></div>
        <div class="card-top">
            <div class="govt-logo">
                <div class="emblem">🏛️</div>
                <div class="title-group">
                    <h2>National Health Authority</h2>
                    <p>Ayushman Bharat Digital Mission (ABDM)</p>
                </div>
            </div>
            <div class="abdm-badge">
                ABHA
            </div>
        </div>

        <div class="card-body">
            {{-- Photo --}}
            <div class="photo-container">
                @php
                    $photo = $patient->abdm_profile['profilePhoto'] ?? $patient->abdm_profile['photo'] ?? null;
                @endphp
                @if ($photo)
                    <img src="data:image/jpeg;base64,{{ $photo }}" alt="Photo" />
                @else
                    <div style="font-size: 40px; color: #94a3b8;">👤</div>
                @endif
            </div>

            {{-- Details --}}
            <div class="details-col">
                <h3 class="patient-name">{{ $patient->name }}</h3>
                <div class="info-row">
                    <span class="info-label">UHID:</span>
                    <span class="info-val">{{ $patient->id }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Gender:</span>
                    <span class="info-val">{{ $patient->sex ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Age / DOB:</span>
                    <span class="info-val">{{ $patient->age ? $patient->age . ' Yrs' : ($patient->dob ?? 'N/A') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Mobile:</span>
                    <span class="info-val">{{ substr($patient->mobile, 0, 3) }}****{{ substr($patient->mobile, -3) }}</span>
                </div>
            </div>

            {{-- QR Code --}}
            <div class="qr-col">
                <div class="qr-box">
                    @php
                        $qrContent = urlencode("ABHA:" . ($patient->abha_number ?? '') . ",Name:" . $patient->name . ",Address:" . ($patient->abha_address ?? ''));
                        $qrImg = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={$qrContent}";
                    @endphp
                    <img src="{{ $qrImg }}" alt="ABHA QR Code" />
                </div>
                <span style="font-size: 9px; color: #64748b; margin-top: 4px; font-weight: bold;">Scan to Verify</span>
            </div>
        </div>

        <div class="card-footer">
            <div class="abha-number-box">
                <span class="abha-number-label">ABHA Number</span>
                <span class="abha-number-value">{{ $patient->formatted_abha_number ?? ($patient->abha_number ?: 'NOT LINKED') }}</span>
            </div>
            <div class="abha-address-box">
                <span class="abha-number-label">ABHA Address</span>
                <div class="abha-address-value">{{ $patient->abha_address ?: 'Not Assigned' }}</div>
            </div>
        </div>
    </div>
</body>
</html>
