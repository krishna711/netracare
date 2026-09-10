<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function index()
    {
        return view('welcome');
    }

    public function pay()
    {
        return view('pay');
    }

    public function storePay(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'age' => 'required',
            'sex' => 'required',
            'city' => 'required',
            'mobile' => 'required',
            'appointment_date' => 'required',
            'transfer_type' => 'required',
            'upi_id' => 'required_if:transfer_type,UPI',
            'upi_txn_number' => 'required',
        ]);

        \App\Models\OnlineAppointment::create([
            'patient_name' => $request->name,
            'age' => $request->age,
            'sex' => $request->sex,
            'city' => $request->city,
            'mobile' => $request->mobile,
            'appointment_date' => $request->appointment_date,
            'transfer_type' => $request->transfer_type,
            'upi_id' => $request->upi_id,
            'upi_txn' => $request->upi_txn_number,
            'confirm' => 0,
            // 'visited' => 0, // This might not be in the fillable or needed if default is 0
        ]);

        return view('pay', ['success' => true]);
    }
}
