<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\IpdV2;

class PrintController extends Controller
{
    public function printPatient(Request $request, $id)
    {
        $patient = Patient::findOrFail($id);
        return view('print.patient', compact('patient'));
    }

    public function printPatientHistory(Request $request, $id)
    {
        $patient = Patient::findOrFail($id);
        $appointments = Appointment::where('patient_id', $id)->get();
        $consultations = Consultation::where('patient_id', $id)->get();
        return view('print.patienthistory', compact('patient', 'appointments', 'consultations'));
    }

    public function printPatientDetails(Request $request, $id, $aid)
    {
        $option = explode(':', rtrim($request->option, ':'));
        $patient = Patient::findOrFail($id);
        $appointment = Appointment::findOrFail($aid);
        $doctor = Doctor::find($appointment->doctor_id);
        $visit = Consultation::where('patient_id', $id)->where('appointment_id', $aid)->first();

        return view('print.patientdetails', compact('patient', 'appointment', 'visit', 'doctor', 'option'));
    }

    public function printEyeCard(Request $request, $id, $aid)
    {
        $patient = Patient::findOrFail($id);
        $appointment = Appointment::findOrFail($aid);
        $visit = Consultation::where('patient_id', $id)->where('appointment_id', $aid)->first();

        return view('print.printcard', compact('patient', 'appointment', 'visit'));
    }

    public function printReceipt(Request $request, $id)
    {
        $payment = \App\Models\Payment::findOrFail($id);
        $patient = Patient::find($payment->patient_id);
        return view('print.receipt', compact('payment', 'patient'));
    }

    public function printIpdDischarge(Request $request, $id, $aid)
    {
        $patient     = Patient::findOrFail($id);
        $appointment = Appointment::findOrFail($aid);
        $doctor      = Doctor::find($appointment->doctor_id);
        $ipd         = IpdV2::where('patient_id', $id)->where('appointment_id', $aid)->first();

        return view('print.ipd_discharge', compact('patient', 'appointment', 'doctor', 'ipd'));
    }

    public function printAbhaCard(Request $request, $id)
    {
        $patient = Patient::findOrFail($id);
        return view('print.abhacard', compact('patient'));
    }
}
