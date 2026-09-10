<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Patient - {{$patient->name}}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- <link rel="stylesheet" type="text/css" media="screen" href="main.css" />
    <script src="main.js"></script> -->
    <style>
        body { font-family: Georgia; }
        .header {
            width:100%;
            max-width:100%;    
        }
        table { width:100%; }
        table tr td { font-size:14px; font-weight:bold;}
        footer {
            width:98%;
            text-align:center;
            padding:2%;
            overflow:hidden;
            bottom:0;
            position:absolute;
            border-top:1px solid #000000;
            font-size:10px;
        }
    </style>
</head>
<body>
<div style="text-align:right;width:100%;max-width:100%;top:0;">Reg No: {{$hospitalSettings['registration_number']}}
{{$hospitalSettings['banner_1']}}
@if(!empty($hospitalSettings['banner_1']))
    <img class="header" src="{{ asset('storage/' . $hospitalSettings['banner_1']) }}" alt="Top Banner" width="100%">
@else
<img class="header" src="/images/header-letter.jpg" />   
@endif

</div>

<table>
    <tr>
    <td align="left">{{$patient->name}} ( N-{{str_pad($patient->id,6,'0',STR_PAD_LEFT)}} ) 
    {{$patient->age}}/{{$patient->sex}}</td>
    <td align="right">Date: <?php echo date('d-M-Y'); ?></td>
    </tr>
</table>
<footer> {{$hospitalSettings['address_english']}}<br />
Contact for Appointment: {{$hospitalSettings['phone_1']}}, {{$hospitalSettings['phone_2']}}, {{$hospitalSettings['phone_3']}} Time: {{$hospitalSettings['timings_english']}}
</footer>
<script>window.print();</script>



</body>
</html>