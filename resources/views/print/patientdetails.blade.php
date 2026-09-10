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
        p { font-size:12px;}
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
<?php if(in_array('disablebanner',$option)) { ?>
<div style="text-align:right;width:100%;max-width:100%;top:0;">Reg No: {{$hospitalSettings['registration_number']}}</div>
@if(!empty($hospitalSettings['banner_1']))
    <img class="header" src="{{ asset('storage/' . $hospitalSettings['banner_1']) }}" alt="Top Banner" width="100%">
@else
    <img class="header" src="/images/header-letter-2.jpg" />   
@endif

<?php } else { ?>  
<div style="text-align:right;height:130px;width:100%;max-width:100%;top:0;"></div>
<?php } ?>

<table>
    <tr>
    <td align="left" valign="top">{{$patient->name}} ( N-{{str_pad($patient->id,6,'0',STR_PAD_LEFT)}} ) 
    {{$patient->age}}/{{$patient->sex}}</td>
    <td align="right" valign="top">Date: <?php echo date('d M Y'); ?>
    </td>
    </tr>
</table>
<?php //if(in_array('eyedetails',$option)) {
//var_dump($visit);
if($visit && in_array('eyedetails',$option)) { ?><br />
<table class="eye" style="float:left;width:39%;margin-right:1%;"> 
    <tr>
        <td>Eye</td>    
        <td>Vn</td>
        <td>Vn Gls</td>
        <td>Iop</td>
        <td>AT</td>        
        <td>Sch</td>
    </tr>    
    <tr>
        <td>R</td>
        <td>{{$visit->r_vn}}</td>
        <td>{{$visit->r_vnglass}}</td>
        <td>{{$visit->r_iop}}</td>
        <td>{{$visit->r_at}}</td>        
        <td>{{$visit->r_sch}}</td>
    </tr>    
    <tr>
        <td>L</td>
        <td>{{$visit->l_vn}}</td>
        <td>{{$visit->l_vnglass}}</td>
        <td>{{$visit->l_iop}}</td>
        <td>{{$visit->l_at}}</td>        
        <td>{{$visit->l_sch}}</td>
    </tr>    
</table>
<table class="eye" style="float:left;width:60%;"> 
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
        <td>R</td>
        <td>{{$visit->r_sph}}</td>
        <td>{{$visit->r_cyl}}</td>
        <td>{{$visit->r_axis}}</td>
        <td>{{$visit->r_disvn}}</td>
        <td>{{$visit->r_nearadd}}</td>
        <td>{{$visit->r_nearvn}}</td>
    </tr>    
    <tr>
        <td>L</td>
        <td>{{$visit->l_sph}}</td>
        <td>{{$visit->l_cyl}}</td>
        <td>{{$visit->l_axis}}</td>
        <td>{{$visit->l_disvn}}</td>
        <td>{{$visit->l_nearadd}}</td>
        <td>{{$visit->l_nearvn}}</td>
    </tr>    
</table>

<p style="text-align:left;">IPD: {{$visit->ipd}}</p>
<p style="text-align:left;"><?php echo str_replace(',',' &bull; ',$visit->lens); ?>
<?php } ?>
<?php if(in_array('diagnosis',$option) && $visit && $visit->diagnosis!=NULL && $visit->notes!='null') { ?>
<table style="width:100%;">
    <tr>
        <td valign="top" style="text-align:left;">        
        <h3>Complaints</h3>
        <p><?php echo $visit->complaint; ?></p></td>
    </tr>
</table>
<?php } ?>
<table style="width:100%;">
    <tr>
        <td valign="top" style="text-align:left;">        
        <h3>Medical History</h3>
        <p><?php echo $visit->previous_history; ?></p></td>
    </tr>
</table>
<?php if(in_array('treatment',$option) && $visit && $visit->prescription_text!=NULL ) { ?>
<table style="width:100%;">
    <tr>
        <td valign="top" style="text-align:left;">

        <table class="noline">
        <?php  

            echo '<tr>'."\r\n";
            echo '<td><h3 style="margin:0px;padding:0px;">RE-A/S</h3></td>';
            echo '<td>'.nl2br($visit->prescription_text).'</td>';   
                           
            echo '<td><h3 style="margin:0px;padding:0px;">LE-A/S</h3></td>';
            echo '<td>'.nl2br($visit->tests).'</td>';
            echo '</tr>'."\r\n";
            echo '<tr>'."\r\n";
            echo '<td><h3 style="margin:0px;padding:0px;">RE-P/S</h3></td>';            
            echo '<td>'.nl2br($visit->prescription_text_re).'</td>';

            echo '<td><h3 style="margin:0px;padding:0px;">LE-P/S</h3></td>';                        
            echo '<td>'.nl2br($visit->tests_le).'</td>';
            echo '</tr>'."\r\n";

        ?>
        </table>
        </td>
    </tr>
</table>
<?php } ?>
<?php if(in_array('diagnosis',$option) && $visit && $visit->diagnosis!=NULL) { ?>
<table style="width:100%;">
    <tr>
        <td valign="top" style="text-align:left;">        
        <h3>Diagnosis</h3>
        <p><?php echo $visit->diagnosis; ?></p></td>
    </tr>
</table>
<?php } ?>
<?php if(in_array('treatment',$option) && $visit && $visit->prescription!=NULL && $visit->notes!='null' ) { ?>
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

<?php if($visit && $visit->tests!=NULL && $visit->tests!='null') { ?>
<!--<table style="width:100%;">
    <tr>
        <td valign="top" style="text-align:left;">
        <h3>Tests</h3>
        <p><?php echo $visit->tests; ?></p>
        </td>
    </tr>
</table>-->
<?php } ?>
<?php if($visit && $visit->advice!=NULL && $visit->advice!='null') { ?>
<table style="width:100%;">
    <tr>
        <td valign="top" style="text-align:left;">
        <h3>Advice</h3>
        <p><?php echo $visit->advice; ?></p>
        </td>
    </tr>
</table>
<?php } ?>
<?php if($visit && $visit->notes!=NULL && $visit->notes!='null') { ?>
<table style="width:100%;">
    <tr>
        <td valign="top" style="text-align:left;">
        <h3>Notes</h3>
        <p><?php echo $visit->notes; ?></p>
        </td>
    </tr>
</table>
<?php } ?>
<?php //} ?>
<?php //var_dump($option); exit; ?>
<?php //} ?>
<footer>

<p style="text-align:right;">
<span>Doctor: <?php echo $doctor->name; ?>
<?php if($visit && $visit->followup_date!=NULL && $visit->followup_date!='null' ) { ?>
, Followup Date: <?php echo date('d M Y',strtotime($visit->followup_date)); ?></span>
<?php } ?>
</p>

<span style="float:right;clear:both;"></span>
<div class="footer-line"></div>
{{$hospitalSettings['address_english']}}<br />
Contact for Appointment: {{$hospitalSettings['phone_1']}}, {{$hospitalSettings['phone_2']}}, {{$hospitalSettings['phone_3']}} Time: {{$hospitalSettings['timings_english']}}
</footer>
<script>window.print();</script>
</body>
</html>