<br/>
<div class="row">
                <div class="col-lg-12">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <div>
                        <p><?php echo 'Usuario: '.$_SESSION['user']->NOMBRE?></p>
                        <p><?php echo 'RFC seleccionado: '.$_SESSION['rfc']?></p>
                        <p><?php echo 'Empresa Seleccionada: <b>'.$_SESSION['empresa']['nombre']."</b>"?></p>  
                        <p><?php echo 'Se muestran los XML '.$ide." del mes ".$mes." del ".$anio?></p>
                    </div>
                        </div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered table-hover" id="dataTables-example">
                                    <thead>
                                        <tr>
                                            <th>Ln</th>
                                            <th>Sta</th>
                                            <th>UUID</th>
                                            <th>TIPO</th>
                                            <th>FOLIO</th>
                                            <th>FECHA</th>
                                            <th>RFC RECEPTOR</th>
                                            <th>RFC EMISOR</th>
                                            <th>SUBTOTAL</th>
                                            <th>IVA</th>
                                            <th>RETENCION <br/>IVA</th>
                                            <th>IEPS</th>
                                            <th>RETENCION <br/>IEPS</th>
                                            <th>RETENCION ISR</th>
                                            <th>DESCUENTO</th>
                                            <th>TOTAL</th>
                                            <th>MON</th>
                                            <th>TC</th>
                                            <th>CLASIFICAR</th>
                                            <th>DESCARGA</th>                                            
                                       </tr>
                                    </thead>                                   
                                  <tbody>
                                        <?php $ln=0;
                                            foreach ($info as $key): 
                                            $color='';
                                            $color2= "";
                                            $ln++;
                                            $descSta = '';
                                            if($key->TIPO == 'I'){
                                                $tipo = 'Ingreso';
                                                $color =  'style="background-color: #56df27"';
                                                $color2= "#56df27";
                                            }elseif ($key->TIPO =='E') {
                                                $tipo = 'Egreso';
                                                $color = 'style="background-color:yellow"';
                                                $color2= "yellow";
                                            }elseif($key->TIPO == 'P'){
                                                $tipo = 'Pago';
                                                $color = 'style="background-color:#aee7e3"';
                                                $color2= "#aee7e3";
                                            }else{
                                                $tipo = 'Desconocido';
                                                $color = 'style="background-color:brown"';
                                                $color2= "brown";
                                            }
                                            $rfcEmpresa=$_SESSION['rfc'];
                                            $test= 'npend';
                                            if($key->STATUS == 'P'){
                                                $descSta = 'Pendiente';
                                                $test= 'pend';
                                            }elseif($key->STATUS== 'D'){
                                                $descSta = 'Poliza de Dr para ver la poliza del documento da click en el UUID';
                                                $color = 'style="background-color:#f9fbae"';
                                            }elseif($key->STATUS=='I'){
                                                $descSta = 'Con Poliza de Ingreso para ver las polizas del documento da click en el UUID';
                                                $color = 'style="background-color:#a0ecfb"';
                                            }elseif($key->STATUS=='E'){
                                                $descSta = 'Con Poliza de Egreso para ver las polizas del documento da click en el UUID';
                                                $color = 'style="background-color:#bcffe9"';
                                            }
                                        ?>
                                        <tr class="<?php echo $test?> odd gradeX " <?php echo $color ?> title="<?php echo $descSta?>" id="ln_<?php echo $ln?>" >
                                            <td><?php echo $ln?></td>
                                            <td><?php echo $descSta.'<br/><font color="blue">'.$key->POLIZA.'</font>'?></td>
                                            <td><a href="index.coi.php?action=verPolizas&uuid=<?php echo $key->UUID ?>" target="popup" onclick="window.open(this.href, this.target, 'width=1200,height=1320'); return false"> <?php echo $key->UUID ?></a> </td>
                                            <td><?php echo $tipo?></td>
                                            <td><?php echo $key->SERIE.$key->FOLIO?></td>
                                            <td><?php echo $key->FECHA;?> </td>
                                            <td><?php echo '('.$key->CLIENTE.')  <br/><b>'.$key->NOMBRE.'<b/>';?></td>
                                            <td><?php echo '('.$key->RFCE.')  <br/><b>'.$key->EMISOR.'<b/>'?></td>
                                            <td><?php echo '$ '.number_format($key->SUBTOTAL,2);?></td>
                                            <td><?php echo '$ '.number_format($key->IVA,2);?></td>
                                            <td><?php echo '$ '.number_format($key->IVA_RET,2);?></td>
                                            <td><?php echo '$ '.number_format($key->IEPS,2);?></td>
                                            <td><?php echo '$ '.number_format($key->IEPS_RET,2);?></td>
                                            <td><?php echo '$ '.number_format($key->ISR_RET,2);?></td>
                                            <td><?php echo '$ '.number_format($key->DESCUENTO,2);?></td>
                                            <td><?php echo '$ '.number_format($key->IMPORTE,2);?> </td>
                                            <td><?php echo '<b>'.$key->MONEDA.'<b/>';?> </td>
                                            <td><?php echo '$ '.number_format($key->TIPOCAMBIO,2);?> </td>
                                            <td>
                                                <a href="index.php?action=verXML&uuid=<?php echo $key->UUID?>&ide=<?php echo $ide?>" class="btn btn-info" target="popup" onclick="marcar(<?php echo $ln?>, 'c'); window.open(this.href, this.target, 'width=1800,height=1320'); return false;"> Clasificar </a>
                                                <center><input type="checkbox" name="revision" id="<?php echo $ln?>" value="<?php echo $ln?>" color="<?php echo $color2?>" onclick="marcar(this.value, 'cb')" ></center>
                                            </td>
                                            <form action="index.php" method="POST">
                                                    <input type="hidden" name="factura" value="<?php echo $key->SERIE.$key->FOLIO?>">
                                                <td>
                                                    <a href="/uploads/xml/<?php echo $rfcEmpresa.'/Recibidos/'.$key->RFCE.'/'.$key->RFCE.'-'.$key->SERIE.$key->FOLIO.'-'.$key->UUID.'.xml'?>" 
                                                        download="<?php echo $key->RFCE.'-'.substr($key->FECHA, 0, 10).'-'.number_format($key->IMPORTE,2).'-'.$key->UUID.'.xml'?>"
                                                        >  <img border='0' src='app/views/images/xml.jpg' width='25' height='30'></a>&nbsp;&nbsp;
                                                    <a href="index.php?action=imprimeUUID&uuid=<?php echo $key->UUID?>" onclick="alert('Se ha descargar tu factura, revisa en tu directorio de descargas')"><img border='0' src='app/views/images/pdf.jpg' width='25' height='30'></a>
                                                </td>
                                            </form>
                                        </tr>
                                        </form>
                                        <?php endforeach; ?>
                                 </tbody>
                                </table>
                            </div>
                      </div>
            </div>
        </div>
</div>

<script src="//code.jquery.com/jquery-1.10.2.js"></script>
<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.0/themes/base/jquery-ui.css">
<link rel="stylesheet" href="/resources/demos/style.css">
<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
<script src="https://code.jquery.com/ui/1.12.0/jquery-ui.js"></script>
<script type="text/javascript">


     function marcar(ln, t){
        var renglon = document.getElementById("ln_"+ln)
        var chek = document.getElementById(ln)
        var color = chek.getAttribute("color")
        if(t == 'c'){
            renglon.style.background="#F08080";         
            return;
        }
        if(chek.checked){
            renglon.style.background="#F08080";         
        }else{
            renglon.style.background=color;
        }

    }


</script>