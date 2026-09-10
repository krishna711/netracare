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
        body { font-family: Arial; font-weight:normal; }
        .header {
            width:100%;
            max-width:100%;    
        }
        h1,h2,h3,h4,h5,h6 {
            margin:8px 0px;
            /* font-family: Gorgia; */
        }
        h1 {margin-bottom:0px;padding-bottom:0px;clear:both;}
        small {text-align:center;font-weight:bold;display:block;font-size:16px;}
        h1, h2, h3 { text-align:center;}        
        p { font-size:14px;}
        table { width:100%; }
        table tr td { font-size:14px;}
        table.eye, table.eye td, table.eye th {
            border: 1px solid #999;
            padding:8px 8px;
        }
       

        table.eye {
            width:100%;
            border-collapse: collapse;
            margin-bottom:10px;
        }

        table.eye th {
        text-align: left;
        }
        footer {
            width:98%;
            text-align:center;
            padding-top:2%;
            padding-bottom:2%;
            overflow:hidden;
            /* bottom:0;
            position:absolute; */

            font-size:10px;
        }

        .heading {
            background-color:green;
            color:#fff;
            border:1px solid green!important;
        }

        .heading td {
            border:1px solid green!important;
        } 

        .header-line {
            margin-top:5px; padding-bottom:5px; 
            border-top:1px solid #000000;
        }        
        footer .footer-line {
            padding-top:5px; padding-bottom:5px; 
            border-top:1px solid #000000;
        }
    </style>
</head>
<body>
<h1>{{$hospitalSettings['title_hindi']}} </h1>
<div class="header-line"></div>
<h3>Patent Information</h3>
<table class="eye">
    <tr>
    <td align="left" valign="top"><strong>Name:</strong> {{$patient->name}} </td>
    <td align="left" valign="top"><strong>ID:</strong> ( N-{{str_pad($patient->id,6,'0',STR_PAD_LEFT)}} ) </td>
    <td align="left" valign="top"><strong>Age:</strong> {{$patient->age}} </td>
    <td align="left" valign="top"><strong>Sex:</strong> {{$patient->sex}} </td>
    <td align="left" valign="top"><strong>Phone:</strong> {{$patient->mobile}} </td>    
    </tr>
</table>
<h3>Appointments</h3>
<?php foreach($appointments as $appointment) { ?>
<table class="eye">

    <tr class="heading">
    <td align="left" valign="top"><?php echo date('d M Y',strtotime($appointment->appointment_time)); ?></td>
    </tr>

    <?php foreach($consultations as $consultation) {
    if($consultation->appointment_id == $appointment->id) {
    ?>
    <tr>
    <td align="left" valign="top">
    <!-- <h4>Diagnosis</h4>
    <p><?php echo $consultation->diagnosis; ?></p> -->
    <h4>Prescriptions</h4>
    <p><?php echo $consultation->prescriptions; ?></p>
    </td>
    </tr>
    <tr>
    <td>
    <h4>Eye Card Details</h4>

    <table class="eye"> 
    <tr>
        <td><strong>Eye</strong></td>
        <td><strong>Vn</strong></td>
        <td><strong>VnGls</strong></td>                
        <td><strong>Sph.</strong></td>
        <td><strong>Cyl.</strong></td>
        <td><strong>Axis</strong></td>
        <td><strong>Dis. Vn.</strong></td>
        <td><strong>Near Add.</strong></td>
        <td><strong>Near Vn.</strong></td>
        <td><strong>IOP</strong></td>
        <td><strong>AT</strong></td>
        <td><strong>CCT</strong></td>  
        <td><strong>K1</strong></td> 
        <td><strong>K2</strong></td>                             
    </tr>    
    <tr>
        <td><strong>RE</strong></td>
        <td>{{$consultation->r_vn}}</td>
        <td>{{$consultation->r_vnglass}}</td>                
        <td>{{$consultation->r_sph}}</td>
        <td>{{$consultation->r_cyl}}</td>
        <td>{{$consultation->r_axis}}</td>
        <td>{{$consultation->r_disvn}}</td>
        <td>{{$consultation->r_nearadd}}</td>
        <td>{{$consultation->r_nearvn}}</td>
        <td>{{$consultation->r_iop}}</td>
        <td>{{$consultation->r_at}}</td>
        <td>{{$consultation->r_sch}}</td> 
        <td></td>
        <td></td>                 
    </tr>    
    <tr>
        <td><strong>LE</strong></td>
        <td>{{$consultation->l_vn}}</td>
        <td>{{$consultation->l_vnglass}}</td>          
        <td>{{$consultation->l_sph}}</td>
        <td>{{$consultation->l_cyl}}</td>
        <td>{{$consultation->l_axis}}</td>
        <td>{{$consultation->l_disvn}}</td>
        <td>{{$consultation->l_nearadd}}</td>
        <td>{{$consultation->l_nearvn}}</td>
        <td>{{$consultation->l_iop}}</td>
        <td>{{$consultation->l_at}}</td>
        <td>{{$consultation->l_sch}}</td> 
        <td></td>
        <td></td>                  
    </tr>    
</table>

<p style="text-align:left;"><strong>Technician:</strong> {{$consultation->technician}}</p>
<p style="text-align:left;"><strong>IPD:</strong> {{$consultation->ipd}}</p>
<p style="text-align:left;"><?php echo str_replace(',',' &bull; ',$consultation->lens); ?></p>
<p style="text-align:left;"><strong>Complaint:</strong> {{$consultation->complaint}}</p>
<p style="text-align:left;"><strong>Medical History:</strong> {{$consultation->previous_history}}</p>
<p style="text-align:left;"><strong>RE-A/S:</strong> {{$consultation->prescription_text}} <strong>LE-A/S:</strong> {{$consultation->tests}} <strong>RE-P/S:</strong> {{$consultation->prescription_text_re}} <strong>LE-P/S:</strong> {{$consultation->tests}}</p>
<p style="text-align:left;"><strong>Diagnosis:</strong> {{$consultation->diagnosis}}</p>
<p style="text-align:left;"><strong>Prescription:</strong><br /> {{nl2br(str_replace("|"," - ",$consultation->prescription))}}</p>
<p style="text-align:left;"><strong>Advice:</strong> {{$consultation->advice}}</p>
<p style="text-align:left;"><strong>Notes:</strong> {{$consultation->notes}}</p>
<p style="text-align:left;"><strong>Followup Date:</strong> {{$consultation->followup_date}}</p>

</td></tr>

    <?php } } ?>

</table>
<?php } ?>


<footer>




<span style="float:right;clear:both;"></span>
<!-- <div class="footer-line"></div>
<h2>Block-A, First Floor, Surendra Landmark, Near Aashima Mall, Bhopal - 462026</h2> -->
</footer>




</body>
</html>