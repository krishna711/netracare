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
        body { font-family: Georgia;  }
        .header {
            width:100%;
            max-width:100%;    
        }
        h1 {margin-bottom:0px;padding-bottom:0px;clear:both;}
        small {text-align:center;font-weight:bold;display:block;font-size:16px;}
        h1, h2, h3 { text-align:center;}        
        p { font-size:14px;}
        table { width:100%; }
        table tr td { font-size:14px; font-weight:bold;}
        table.eye, table.eye td, table.eye th {
        border: 1px solid black;
        padding:8px;
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
        .header-line {
            margin-top:25px; padding-bottom:25px; 
            border-top:1px solid #000000;
        }        
        footer .footer-line {
            padding-top:5px; padding-bottom:5px; 
            border-top:1px solid #000000;
        }
    </style>
</head>
<body>
<table>
<tr>
<td><img style="display:block;margin:0 auto; width:150px;float:right;" src="/images/logo.jfif" /></td>
<td align="center"><h1>{{$hospitalSettings['title_hindi']}} </h1>
<small>NABH Accredited Hospital</small>
<small>एडवांस मोतियाबिंद आपरेशन एवं रेटीना केयर  सेंटर </small></td>
<td><img style="display:block;margin:0 auto; width:150px;float:right;" src="/images/nabh-logo.jpg" /></td>
</tr>
</table>



<div style="clear:both;"></div>
<div class="header-line"></div>
<table>
    <tr>
    <td align="left" valign="top">{{$patient->name}} ( N-{{str_pad($patient->id,6,'0',STR_PAD_LEFT)}} ) 
    {{$patient->age}}/{{$patient->sex}}</td>
    <td align="right" valign="top">Date: <?php echo date('d M Y'); ?><?php if($visit && $visit->followup_date!=NULL) { ?><br />Followup: <?php echo date('d M Y',strtotime($visit->followup_date)); ?><?php } ?></td>
    </tr>
</table>
<?php if($visit) { ?>
<table class="eye"> 
    <tr>
        <td>Eye</td>
        <td>Sph.</td>
        <td>Cyl.</td>
        <td>Axis</td>
        <td>Dis. Vn.</td>
        <td>Near Add.</td>
        <td>Near Vn.</td>
    </tr>    
    <tr>
        <td>OD</td>
        <td>{{$visit->r_sph}}</td>
        <td>{{$visit->r_cyl}}</td>
        <td>{{$visit->r_axis}}</td>
        <td>{{$visit->r_disvn}}</td>
        <td>{{$visit->r_nearadd}}</td>
        <td>{{$visit->r_nearvn}}</td>
    </tr>    
    <tr>
        <td>OS</td>
        <td>{{$visit->l_sph}}</td>
        <td>{{$visit->l_cyl}}</td>
        <td>{{$visit->l_axis}}</td>
        <td>{{$visit->l_disvn}}</td>
        <td>{{$visit->l_nearadd}}</td>
        <td>{{$visit->l_nearvn}}</td>
    </tr>    
</table>
<p style="text-align:left;">IPD: {{$visit->ipd}}</p>
<p style="text-align:left;"><?php echo str_replace(',',' &bull; ',$visit->lens); ?></p>
<?php } else { ?>
<h2>Consultation details not found!</h2>
<?php } ?>
<footer>

<span style="float:right;clear:both;"></span>
<h2>Time: {{$hospitalSettings['timings_english']}}</h2>
<div class="footer-line"></div>
<h2>{{$hospitalSettings['address_english']}}<br />Phone: {{$hospitalSettings['phone_1']}}</h2>
<h1>{{$hospitalSettings['info_english']}}</h1>
</footer>
<?php if($visit) { ?>
<script>window.print();</script>
<?php } ?>



</body>
</html>