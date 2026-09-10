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
        p { font-size:14px;}
        table { width:100%; }
        table tr td { font-size:14px; font-weight:normal;}
        table.eye, table.eye td, table.eye th {
        border: 1px solid black;
        padding:8px;
        }

        table.eye {
        width:auto;
        border-collapse: collapse;
        margin-bottom:10px;
        }

        table.eye th {
        text-align: left;
        }
        table.med, table.med td, table.med th {
        border: 1px solid black;
        padding:8px;
        }

        table.med {
        width:auto;
        border-collapse: collapse;
        margin-bottom:10px;
        }

        table.med th {
        text-align: left;
        }
        
        table.noline, table.noline td, table.noline th {
        border: 0px solid black;
        padding:5px 20px 0px 0px;
        }

        table.noline {
        width:auto;
        border-collapse: collapse;
        margin-bottom:10px;
        }

        table.noline th {
        text-align: left;
        }           
        
        footer {
            width:98%;
            text-align:center;
            padding-top:2%;
            padding-bottom:2%;
            overflow:hidden;
            bottom:0;
            position:absolute;

            font-size:10px;
        }
        footer .footer-line {
            padding-top:5px; padding-bottom:5px; 
            border-top:1px solid #000000;
        }
    </style>
</head>
<body>
<img class="header" src="/images/header-letter.jpg" />   
<table>
    <tr>
    <td align="left" valign="top">{{$patient->name}} ( N-{{str_pad($patient->id,6,'0',STR_PAD_LEFT)}} ) 
    {{$patient->age}}/{{$patient->sex}}</td>
    <td align="right" valign="top">Date: <?php echo date('d M Y'); ?>
    <!--<?php if($visit && $visit->followup_date!=NULL) { ?>-->
    <!--<br />Followup: <?php echo date('d M Y',strtotime($visit->followup_date)); } ?>-->
    <?php if($visit && $visit->referred_by!=NULL) { ?>    
    <br />Reffered By: {{$visit->referred_by}}
    <?php } ?>
    </td>
    </tr>
</table>
<?php if(in_array('diagnosis',$option) && $visit && $visit->diagnosis!=NULL) { ?>
<table style="width:100%;">
    <tr>
        <td valign="top" style="text-align:left;">        
        <h3>Diagnosis</h3>
        <p><?php echo $visit->diagnosis; ?></p></td>
    </tr>
</table>
<?php } ?>
<table style="width:100%;">
    <tr>
        <td valign="top" style="text-align:left;">        
        <h3>Previous History</h3>
        <p><?php echo ($visit && $visit->previous_history!=NULL)?$visit->previous_history:''; ?></p></td>
    </tr>
</table>
<?php if(in_array('treatment',$option) && $visit && ($visit->prescription_text!=NULL || $visit->notes!='null') ) { ?>
<table style="width:100%;">
    <tr>
        <td valign="top" style="text-align:left;">
        <h3>Treatment</h3>
        <table class="noline">
        <?php  

            echo '<tr><td>'.nl2br($visit->prescription_text).'</td></tr>'."\r\n";
        
        ?>
        </table>
        </td>
    </tr>
</table>
<?php } else if(in_array('treatment',$option) && $visit && ($visit->prescription!=NULL || $visit->notes!='null') ) { ?>
<table style="width:100%;">
    <tr>
        <td valign="top" style="text-align:left;">
        <h3>Treatment</h3>
        <table class="noline">
        <?php  
        $prescription = explode('~',$visit->prescription);
        foreach($prescription as $p) {
            $medicines = explode('|',$p);
            //foreach($medicines as $m) {
            echo '<tr><td>'.$medicines[0].'</td><td>'.$medicines[1].'</td><td>'.$medicines[2].'</td><td>'.$medicines[3].'</td><td>'.(isset($medicines[4])?$medicines[4]:'').'</td></tr>'."\r\n";
            //}
        }
        ?>
        </table>
        </td>
    </tr>
</table>
<?php } ?>
<?php if($visit && $visit->notes!=NULL && $visit->notes!='null' ) { ?>
<table style="width:100%;">
    <tr>
        <td valign="top" style="text-align:left;">
        <h3>Notes</h3>
        <p><?php echo $visit->notes; ?></p>
        </td>
    </tr>
</table>
<?php } ?>
<?php //var_dump($option); ?>
<footer>
<?php if(in_array('eyedetails',$option)) {
if($visit) { 
?>
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
<p style="text-align:left;float:left;width:60%;"><?php echo str_replace(',',' &bull; ',$visit->lens); ?>
</p>
<?php } ?>
<?php } ?>
<?php if($visit && ($visit->followup_date!=NULL || $visit->followup_date!="")) { ?>
<p style="float:right;">Followup Date: <?php echo date('d M Y',strtotime($visit->followup_date)); ?><?php } ?></p>
<div style="clear:both;"></div>
<div class="footer-line"></div>
Netrika Netralaya - Block-A, First Floor, Surendra Landmark, Near Aashima Mall, Bhopal - 462026<br />
Contact for Appointment: 0755-4225186, 9893086699, Time: Morning 10AM To 1:30PM & Evening 5PM To 8:30PM, Sunday Closed
</footer>
<script>window.print();</script>



</body>
</html>