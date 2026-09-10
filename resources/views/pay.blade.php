<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Appointment - Netrika Netralaya</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-900 min-h-screen">
    <div class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <img class="h-20 mx-auto mb-6" src="/images/logo.jfif" alt="Netrika Netralaya">
            <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight">Online Appointment</h1>
            <p class="mt-4 text-lg text-slate-600">Quick and easy appointment booking for your eye care needs</p>
        </div>

        @if(isset($success))
            <div class="glass border border-emerald-200 rounded-3xl p-12 text-center shadow-xl">
                <div
                    class="bg-emerald-100 text-emerald-600 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h2 class="text-3xl font-bold text-slate-900 mb-4">Appointment Submitted!</h2>
                <p class="text-slate-600 text-lg mb-8">Thank you for choosing Netrika Netralaya. Your appointment will be
                    confirmed shortly via SMS.</p>
                <a href="/"
                    class="inline-flex items-center px-8 py-4 border border-transparent text-lg font-semibold rounded-2xl text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-lg hover:shadow-indigo-200">
                    Back to Home
                </a>
            </div>
        @else
            <form action="/pay" method="POST" class="space-y-8">
                @csrf
                <div class="glass border border-slate-200 rounded-3xl shadow-xl overflow-hidden">
                    <div class="bg-slate-900 p-8">
                        <h2 class="text-2xl font-bold text-white">Patient Information</h2>
                    </div>
                    <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Patient Full Name</label>
                            <input type="text" name="name" required
                                class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition outline-none"
                                placeholder="e.g. John Doe">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Age</label>
                            <input type="number" name="age" required
                                class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition outline-none"
                                placeholder="e.g. 45">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Gender</label>
                            <select name="sex" required
                                class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition outline-none">
                                <option value="">Select Gender</option>
                                <option>Male</option>
                                <option>Female</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">City</label>
                            <input type="text" name="city" required
                                class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition outline-none"
                                placeholder="e.g. Bhopal">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Mobile Number</label>
                            <input type="tel" name="mobile" required
                                class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition outline-none"
                                placeholder="9999999999">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Appointment Date</label>
                            <input type="date" name="appointment_date" required
                                class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition outline-none">
                        </div>
                    </div>
                </div>

                <div class="glass border border-slate-200 rounded-3xl shadow-xl overflow-hidden">
                    <div class="bg-slate-900 p-8">
                        <h2 class="text-2xl font-bold text-white">Payment Selection</h2>
                    </div>
                    <div class="p-8 space-y-8">
                        <div class="flex gap-6">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="transfer_type" value="UPI" checked class="peer hidden">
                                <div
                                    class="p-6 border-2 border-slate-100 rounded-2xl peer-checked:border-indigo-600 peer-checked:bg-indigo-50 transition hover:bg-slate-50">
                                    <div class="font-bold text-lg mb-1">UPI Payment</div>
                                    <div class="text-slate-500 text-sm italic">GPay, PhonePe, Paytm, etc.</div>
                                </div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="transfer_type" value="Bank Transfer" class="peer hidden">
                                <div
                                    class="p-6 border-2 border-slate-100 rounded-2xl peer-checked:border-indigo-600 peer-checked:bg-indigo-50 transition hover:bg-slate-50">
                                    <div class="font-bold text-lg mb-1">Bank Transfer</div>
                                    <div class="text-slate-500 text-sm italic">Direct Bank Transaction</div>
                                </div>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-2">UPI ID</label>
                                    <input type="text" name="upi_id"
                                        class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 transition"
                                        placeholder="e.g. name@upi">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-2">Transaction
                                        Number</label>
                                    <input type="text" name="upi_txn_number" required
                                        class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 transition"
                                        placeholder="Last 6 digits">
                                </div>
                                <div class="p-6 bg-indigo-50 rounded-2xl border border-indigo-100">
                                    <strong class="text-indigo-900 block mb-2">Bank Details:</strong>
                                    <p class="text-indigo-800 text-sm leading-relaxed">
                                        A/C No: 119205001037<br>
                                        Name: Netrika Netralya<br>
                                        IFSC: ICICI0001192<br>
                                        Branch: Hoshangabad Road, Bhopal
                                    </p>
                                </div>
                            </div>
                            <div class="text-center">
                                <label class="block text-sm font-semibold text-slate-700 mb-4">Scan QR to Pay</label>
                                <div class="bg-white p-4 rounded-3xl shadow-md inline-block">
                                    <img class="max-w-[200px]" src="/images/619303f9-684b-40fa-97d4-1cf3a2d2cc6f.jfif"
                                        alt="QR Code">
                                </div>
                                <div class="mt-4">
                                    <span class="text-slate-500 text-sm block mb-1">OR Pay via PhonePe link:</span>
                                    <a href="https://phon.pe/916783364"
                                        class="text-indigo-600 font-semibold hover:underline">phon.pe/916783364</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center pb-12">
                    <button type="submit"
                        class="inline-flex items-center px-12 py-5 border border-transparent text-xl font-bold rounded-2xl text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-2xl hover:shadow-indigo-300 transform hover:-translate-y-1">
                        Confirm Appointment
                    </button>
                    <p class="mt-6 text-slate-500 text-sm">
                        Netrika Netralaya, Block A, 1st Floor, Surendra Landmark,<br>
                        Near Ashima Mall, Bhopal, MP 462026
                    </p>
                </div>
            </form>
        @endif
    </div>
</body>

</html>