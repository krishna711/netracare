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
        body { font-family: Arial; }
        .header {
            width:auto;
            margin:0 auto;
            display:block;
            height:50px;   
        }
        .regno {float:right;positon:absolute;right:0;top:0;}
        h1 {margin-bottom:0px;padding-bottom:0px;clear:both;}
        small {text-align:center;font-weight:bold;display:block;font-size:16px;}
        h1, h2, h3 { text-align:center;}
        table { width:100%; }
        div { font-size:14px; }
        table tr td { font-size:14px; padding-bottom:10px;}
        footer {
            width:98%;
            text-align:center;
            padding:2%;
            overflow:hidden;
            margin-top:20px;
            font-size:12px;
            /* bottom:0;
            position:absolute; */
            border-top:1px solid #000000;
        }
        strong {
            text-transform:uppercase;
            /* text-decoration:underline; */

        }
    </style>
<?php 

?>
</head>
<body>
<div class="regno">Reg No: {{$hospitalSettings['registration_number']}}</div>
<!-- <img class="header" src="/images/header-receipt.jpg" /> -->
 <h1>{{$hospitalSettings['title_hindi']}}</h1>
 <small>अत्याधुनिक सुविधाओं से सुसज्जित आँखों का अस्पताल</small>
 <h3>Receipt</h3>
<div style="border-bottom:1px solid #000;overflow:hidden;padding-bottom:10px;margin-bottom:10px;">
    <div style="float:left;text-align:left;width:50%;">Receipt No: {{$payment->id}}</div>
    <div style="float:right;text-align:right;width:50%;">Date: {{$payment->created_at}}</div>
</div>

<table>
    <tr>
        <td align="left">Recieved From <strong>{{$patient->name}} ( N-{{str_pad($patient->id,6,'0',STR_PAD_LEFT)}})</strong></td>
        <td align="right">The Amount of <strong>{{$payment->amount}} INR</strong></td>
    </tr>
    <tr>
        <td align="left">For <strong><?php echo $payment->description; ?></strong></td>
        <td align="right">By <strong><?php echo $payment->mode; ?></strong></td>
    </tr> 
    <tr>
        <td align="left" style="" colspan="2">
        <div style="border-bottom:1px solid #000;">

    </div></td>
    </tr>
    <tr>
        <td align="left" style="" colspan="2">
        <div style="border-bottom:1px solid #000;padding-bottom:15px;text-transform:capitalize;">Amount in Words: 
        <?php 
        $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
        echo $f->format($payment->amount);
        ?> Rupees Only
    </div></td>
    </tr>          
    <tr>
        <td align="left"></td>
        <td align="right" style="padding-top:50px;">Recieved By: ________________________________</td>
    </tr>       
    <!-- <tr>
    <td align="left">Patient: {{$patient->name}} ( N-{{str_pad($patient->id,6,'0',STR_PAD_LEFT)}} ) 
    Age/Sex: {{$patient->age}}/{{$patient->sex}}</td>
    <td align="right">Date: <?php echo date('d-M-Y'); ?></td>
    </tr> -->
</table>
<footer>
    {{$hospitalSettings['address_english']}}<br />Phone: {{$hospitalSettings['phone_1']}}<br />
    Timings: {{$hospitalSettings['timings_english']}}
</footer>
<script>window.print();</script>



</body>
</html>