<?php

require_once 'app/model/database.coi.php';
/* Clase para hacer uso de database */
class CoiDAO extends DataBaseCOI {

    function traeParametros(){
        $this->query="SELECT * FROM PARAMEMP";
        $res = $this->EjecutaQuerySimple();
        $row=ibase_fetch_object($res);
        return $row;
    }

    function traeConfiguracion(){
        $data=array();
        $this->query="SELECT * FROM FTC_PARAM_COI";
        $res=$this->EjecutaQuerySimple();
        while ($tsArray=ibase_fetch_object($res)){
            $data=$tsArray;
        }
        return $data; 
    }

    function traeCuentaCliente($info, $ide){
        foreach ($info as $key) {
            if($ide == 'Emitidos'){
                $rfce=$key->CLIENTE;
            }else{
                $rfce=$key->RFCE;
            }
            $cuenta=$key->CUENTA_CONTABLE;       
        }
        $this->query="SELECT * FROM CUENTAS19 WHERE TRIM(NUM_CTA)=TRIM('$cuenta')";
        $res=$this->EjecutaQuerySimple();
        $row=ibase_fetch_object($res);
        if($row){
            $cuenta = $row->NUM_CTA;
            $nombre = $row->NOMBRE;
            return trim($rfce).':'.$nombre.'->'.$cuenta;
        }else{
            return 'Sin Cuenta Actual';
        }
    }

    function traeCuentasSAT($info){
        $data=array();
        foreach ($info as $key) {
            $rfc=$key->RFC;       
            $cveSat=$key->CLAVE_SAT;
            $uniSat=$key->UNIDAD_SAT;
            $cuenta=$key->CUENTA_CONTABLE;
            $this->query="SELECT * FROM CUENTAS19 WHERE TRIM(NUM_CTA)=TRIM('$cuenta') ";
            $res=$this->EjecutaQuerySimple();
            while($tsArray=ibase_fetch_object($res)){
                $data[]=$tsArray;
            }
        }
        return $data;
    }

    function traeCatalogoCuentas($tipo, $ide){
        $data=array();
        $numcta="";
        if($tipo == 'V' and $ide == 'Recibidos'){
            $numcta = "where (cuenta starting with ('2') or cuenta starting with ('21') or cuenta starting with ('140')) and tipo = 'D' order by nombre";
        }elseif($tipo =='G'){
            $numcta = "where (cuenta starting with ('5') or cuenta starting with ('6') or cuenta starting with ('7')) and tipo = 'D' order by cuenta";
        }elseif($tipo =='V' and $ide == 'Emitidos'){
            $numcta = "where (cuenta starting with ('1') or cuenta starting with ('11') or cuenta starting with ('1150')) and tipo = 'D' order by nombre";
        }
        $this->query="SELECT C.* FROM CUENTAS_FTC C $numcta";
        //echo $this->query;
        $res=$this->EjecutaQuerySimple();
        while($tsArray=ibase_fetch_object($res)){
            $data[]=$tsArray;
        }
        return $data;
    }

    function creaParam($cliente, $partidas){
        $usuario=$_SESSION['user']->NOMBRE;
        $cliente = explode(":", $cliente);
        $rfc = $cliente[0];
        $cuenta = $cliente[1];
        $uuid = $cliente[2];
        $rfc_e = $cliente[3];

        $this->query="SELECT COALESCE(MAX(ID), 0) + 1 AS FOLIO FROM FTC_PARAMETROS";
        $res=$this->EjecutaQuerySimple();
        $row=ibase_fetch_object($res);
        $num_para = $row->FOLIO;

        $this->query="INSERT INTO FTC_PARAMETROS (ID, UUID, RFC_R, RFC_E, FECHA_ALTA, USUARIO, STATUS )
                            VALUES(NULL, '$uuid', '$rfc', '$rfc_e', current_timestamp,'$usuario', 1 )";
        $this->grabaBD();

        $this->query="INSERT INTO FTC_CUENTAS_SAT (ID, CLAVE_SAT, CUENTA_COI, STATUS, RFC_CLIENTE, RFC_PROVEEDOR, UNIDAD_SAT, FTC_NUM_PARAM) 
                        VALUES(null,'MXN','$cuenta',1,'$rfc','$rfc_e','MX', $num_para)";
        //echo '<br/>'.$this->query;
        if($this->grabaBD()){
            /// Debemos de asegurarnos que nuestro proveedor solo tenga una cuenta.
            //$this->query="UPDATE CUENTAS18 SET RFC = '' WHERE RFC = '$rfc_e' "
            $this->query="UPDATE CUENTAS19 SET RFC='$rfc_e' where num_cta = '$cuenta'";
            $this->grabaBD();
            $partidas=explode("###", $partidas);
            //var_dump($partidas);
            foreach ($partidas as $key) {
                //1:83111603:E48:640000400040000000003
                //echo 'Valor de partida: '.$key.'<br/>';
                $par=explode(":",$key);
                //var_dump($par);
                    $partida =$par[0];
                    $cve_sat =$par[1];
                    $uni_sat =$par[2];
                    $c_coi   =$par[3];
                    $rfc_e   =$par[4];
                 $this->query="INSERT INTO FTC_CUENTAS_SAT (ID, CLAVE_SAT, CUENTA_COI, STATUS, RFC_CLIENTE, RFC_PROVEEDOR, UNIDAD_SAT, FTC_NUM_PARAM)
                                values(null, '$cve_sat', '$c_coi', 1, '$rfc','$rfc_e', '$uni_sat', $num_para)";
                 //echo '<br/>'.$this->query.'<br/>';
                 $this->EjecutaQuerySimple();
            }
            return array("status"=>'ok',"mensaje"=>"Se inserto correctamente el parametro... del cliente");
        }else{
            $this->query="DELETE from FTC_PARAMETROS where id = $num_para";
            $this->grabaBD();

            return array("status"=>'no',"mensaje"=>"No se pudo guardar, favor de intentarlo nuevamente");
        }
    }

    function validaCuenta($datosCliente){
      
        $rfc = $datosCliente->RFC;
        $nombre = $datosCliente->NOMBRE;

        $this->query= "SELECT iif(MAX(NUM_CTA) is null, 0, max(NUM_CTA)) as valor FROM CUENTAS17 WHERE RFC = '$rfc'";
        $resultado = $this->QueryObtieneDatosN();
        $rowCuenta = ibase_fetch_object($resultado);
        $valCuenta = $rowCuenta->VALOR;
        //echo $valCuenta;
        //echo $this->query;
        //break;
            if($valCuenta ==  0 ){
                $this->query ="SELECT MIN(NUM_CTA) as papa, substring(MAX(num_cta) from 8 for 3) as hija FROM CUENTAS17 WHERE NUM_CTA STARTING WITH ('1150001')";
                $rs = $this->QueryObtieneDatosN();
                $rowCtaDet= ibase_fetch_object($rs);
                $ctaPapa = $rowCtaDet->PAPA;
                $ctaHija = $rowCtaDet->HIJA;
                $nueva = $ctaHija + 1;
                    if(strlen($nueva) == 1){
                        $nueva = '00'.$nueva;
                    }elseif (strlen($nueva) == 2){
                        $nueva = '0'.$nueva;
                    }elseif (strlen($nueva) == 3){
                        $nueva = $nueva;
                    }
                    $cuentaNueva = substr($ctaPapa, 0, 7).$nueva.'00000000003';
                $this->query = "INSERT INTO CUENTAS17 VALUES ('$cuentaNueva', 'A', 'D', substring('$datosCliente->NOMBRE' from 1 for 40), 'N', 1,'N','$ctaPapa', '115000000000000000001', 3, '', 0, '$rfc', '', 0,0, 0, '','N', 0, '', 0,'' )";
                $rs= $this->EjecutaQuerySimple();
                //echo $this->query;
                $this->query = "INSERT INTO SALDOS17 (num_cta, Ejercicio) VALUES ('$cuentaNueva', 2016)";
                $rs = $this->EjecutaQuerySimple();

                $valCuenta = $cuentaNueva;
                //echo $this->query;
                //break;
            }

            return $valCuenta;
        }

    function creaPoliza($tipo, $uuid, $cabecera, $detalle, $impuestos){
        /// Obtenemos la fecha del documento
        $usuario=$_SESSION['user']->USER_LOGIN;
        foreach($cabecera as $cb){
            $periodo=$cb->PERIODO;
            $ejercicio=$cb->EJERCICIO;
            $eje=substr($ejercicio,2);
            $fecha=$cb->FECHA; 
            $proveedor = $cb->NOMBRE;
            $tc = $cb->TIPOCAMBIO;
            $tbPol= 'POLIZAS'.$eje; 
            $tbAux= 'AUXILIAR'.$eje;
            $campo = 'FOLIO'.str_pad($periodo, 2, '0', STR_PAD_LEFT);
        }
        ///creamos el nuevo folio de la poliza y actualizamos para apartarlo
        $this->query="SELECT $campo FROM FOLIOS where tippol='$tipo' and Ejercicio=$ejercicio";
        $res=$this->EjecutaQuerySimple();
        $row= ibase_fetch_object($res);
        $folion = $row->$campo + 1; 
        $folio =str_pad($folion, 5, ' ', STR_PAD_LEFT);

        $this->query="UPDATE FOLIOS SET $campo = $folion where tippol='$tipo' and Ejercicio=$ejercicio";
        $this->queryActualiza();

        if($cb->CLIENTE == $_SESSION['rfc']){
            $nat0='H';
            $nat1='D';
            $con = '';
        }else{
            $nat0='D';
            $nat1='H';
            $con = 'Venta ';
        }
        foreach($cabecera as $pol){
            $concepto = $con.substr($pol->NOMBRE.', '.$pol->DOCUMENTO.', '.$pol->FECHA, 0, 120);
            $cuenta = $pol->CUENTA_CONTABLE;
            $this->query="INSERT INTO $tbPol(TIPO_POLI, NUM_POLIZ, PERIODO, EJERCICIO, FECHA_POL, CONCEP_PO, NUM_PART, LOGAUDITA, CONTABILIZ, NUMPARCUA, TIENEDOCUMENTOS, PROCCONTAB, ORIGEN, UUID, ESPOLIZAPRIVADA, UUIDOP) 
                                values ('$tipo','$folio', $periodo, $ejercicio, '$pol->FECHA', '$concepto', 0, '', 'N', 0, 1, 0, substring('PHP $usuario' from 1 for 15),'$uuid', 0, '')";
            $this->EjecutaQuerySimple();
            //echo '<br/>Inserta Poliza:'.$this->query.'<br/>';

            $this->query="INSERT INTO $tbAux (TIPO_POLI, NUM_POLIZ, NUM_PART, PERIODO, EJERCICIO, NUM_CTA, FECHA_POL, CONCEP_PO, DEBE_HABER, MONTOMOV, NUMDEPTO, TIPCAMBIO, CONTRAPAR, ORDEN, CCOSTOS, CGRUPOS, IDINFADIPAR, IDUUID) 
                                values ('$tipo', '$folio', 1, $periodo, $ejercicio, '$cuenta', '$pol->FECHA', '$concepto', '$nat0' , $pol->IMPORTE, 0, $tc, 0, 1, 0, 0, NULL,NULL)";
            $this->EjecutaQuerySimple();  
            //echo '<br/> Inserta Primer Partida'.$this->query.'<br/>';
            /// Validacion para la insercion de UUID.
        }
        $partida = 1;
        foreach ($detalle as $aux) {
            $cuenta = '';
            $partida++;
            $partAux=$aux->PARTIDA;
            $cuenta = $aux->CUENTA_CONTABLE;
            $documento = $aux->DOCUMENTO;
            $concepto = substr($aux->DESCRIPCION.', '.$documento.', '.$proveedor, 0, 120); 

                $this->query="INSERT INTO $tbAux (TIPO_POLI, NUM_POLIZ, NUM_PART, PERIODO, EJERCICIO, NUM_CTA, FECHA_POL, CONCEP_PO, DEBE_HABER, MONTOMOV, NUMDEPTO, TIPCAMBIO, CONTRAPAR, ORDEN, CCOSTOS, CGRUPOS, IDINFADIPAR, IDUUID) 
                                values ('$tipo', '$folio', $partida, $periodo, $ejercicio, '$cuenta','$fecha', '$concepto','$nat1', $aux->IMPORTE - $aux->DESCUENTO, 0, $tc, 0, $partida, 0,0, null, null)";
                $this->EjecutaQuerySimple();   
                //echo $this->query;
                foreach ($impuestos as $imp){
                    //echo '<br/>'.print_r($imp).'<br/>';
                    $impuesto=$imp->IMPUESTO;
                    $tasa = (float)$imp->TASA;
                    $mImp = $imp->MONTO; 
                    $par = $imp->PARTIDA; 
                    $factor =$imp->TIPOFACTOR;
                    $tf = $imp->TIPO;
                    $nom_1 = $aux->DESCRIPCION;

                    if($partAux == $par){
                        $cuenta = '';
                        $parImp = $partida + 1;
                        if($tf=='Retencion'){
                            $this->query="SELECT * FROM FTC_PARAM_COI WHERE impuesto = '$impuesto' and status = 1 and factor = '$factor' and tipo = '$tf' and poliza ='$tipo'";
                        }else{
                            $this->query="SELECT * FROM FTC_PARAM_COI WHERE impuesto = '$impuesto' and status = 1 and factor = '$factor'and tipo = '$tf' and poliza ='$tipo' and tasa=$tasa";
                        }
                        $res=$this->EjecutaQuerySimple();
                        $rowImp = ibase_fetch_object($res);
                        if(!empty($rowImp)){
                            $cuenta = $rowImp->CUENTA_CONTABLE;
                            $nom_1 = $rowImp->NOMBRE; 
                            $nat1= $rowImp->NAT==1? 'H':$nat1;
                                $concepto = substr($nom_1.' de la partida '.$partAux,0,120);
                                $this->query="INSERT INTO $tbAux (TIPO_POLI, NUM_POLIZ, NUM_PART, PERIODO, EJERCICIO, NUM_CTA, FECHA_POL, CONCEP_PO, DEBE_HABER, MONTOMOV, NUMDEPTO, TIPCAMBIO, CONTRAPAR, ORDEN, CCOSTOS, CGRUPOS, IDINFADIPAR, IDUUID) 
                                                values ('$tipo', '$folio', $parImp, $periodo, $ejercicio, '$cuenta','$fecha', '$concepto','$nat1', $mImp, 0, $tc, 0, $parImp, 0,0, null, null)";
                                //echo '<br/>'.$this->query.'<br/>';
                                $this->EjecutaQuerySimple();   
                                $partida++;
                        }else{
                        //    echo 'La definicion del impueso no existe: '.$this->query;
                            $cuenta=$aux->CUENTA_CONTABLE;
                            $nom_1=$aux->DESCRIPCION;
                            $cuenta = $aux->CUENTA_CONTABLE;
                            $concepto = substr($nom_1.' de la partida '.$partAux,0,120);
                            $this->query="UPDATE $tbAux SET montomov = montomov + $imp->MONTO WHERE NUM_CTA = '$cuenta' and NUM_POLIZ = '$folio' and TIPO_POLI = '$tipo' and periodo = $periodo and ejercicio = $ejercicio";
                            /*
                            $this->query="INSERT INTO $tbAux (TIPO_POLI, NUM_POLIZ, NUM_PART, PERIODO, EJERCICIO, NUM_CTA, FECHA_POL, CONCEP_PO, DEBE_HABER, MONTOMOV, NUMDEPTO, TIPCAMBIO, CONTRAPAR, ORDEN, CCOSTOS, CGRUPOS, IDINFADIPAR, IDUUID) 
                                            values ('$tipo', '$folio', $parImp, $periodo, $ejercicio, '$cuenta','$fecha', '$concepto','$nat1', $mImp, 0, $tc, 0, $parImp, 0,0, null, null)";
                            */
                            //echo '<br/>'.$this->query.'<br/>';
                            $this->EjecutaQuerySimple();   
                            //$partida++;
                        }
                    }
                    if($tf=='local'){
                        //$parImp = $partida + 1;
                        $cuenta = $aux->CUENTA_CONTABLE;
                        $concepto = substr($nom_1.' de la partida '.$partAux,0,120);
                        $this->query="UPDATE $tbAux SET montomov = montomov + $imp->MONTO WHERE NUM_CTA = '$cuenta' and NUM_POLIZ = '$folio' and TIPO_POLI = '$tipo' and periodo = $periodo and ejercicio = $ejercicio";
                        /*
                        $this->query="INSERT INTO $tbAux (TIPO_POLI, NUM_POLIZ, NUM_PART, PERIODO, EJERCICIO, NUM_CTA, FECHA_POL, CONCEP_PO, DEBE_HABER, MONTOMOV, NUMDEPTO, TIPCAMBIO, CONTRAPAR, ORDEN, CCOSTOS, CGRUPOS, IDINFADIPAR, IDUUID) 
                                        values ('$tipo', '$folio', $parImp, $periodo, $ejercicio, '$cuenta','$fecha', '$concepto','$nat1', $mImp, 0, $tc, 0, $parImp, 0,0, null, null)";
                        */
                        //echo '<br/>'.$this->query.'<br/>';
                        $this->EjecutaQuerySimple();   
                        //$partida++;
                    }   
                }
        }
        $this->insertaUUID($tipo, $uuid, $pol, $folio, $ejercicio, $periodo);
        return $mensaje= array("status"=>'ok', "mensaje"=>'Se ha creado la poliza', "poliza"=>'Dr'.$folio,"numero"=>$folio,"ejercicio"=>$ejercicio, "periodo"=>$periodo);
    }

    function insertaUUID($tipo, $uuid, $pol, $folio, $ejercicio, $periodo){
        $data=array();
        $eje= substr($ejercicio,-2);
        $this->query="SELECT * FROM AUXILIAR$eje a left join cuentas$eje c on c.num_cta = a.num_cta where c.capturauuid = 1 and a.NUM_POLIZ='$folio' and a.periodo = $periodo and ejercicio = $ejercicio";
        $res=$this->EjecutaQuerySimple();
        //echo $this->query;
        while ($tsArray=ibase_fetch_object($res)) {
            $data[]=$tsArray;
        }
        //echo 'Valor del count de data: '.count($data);
        if(count($data) > 0){
            //echo '<br/> Encontro Datos e intenta la insercion:<br/>';
            foreach ($data as $a) {
                $this->query="INSERT INTO UUIDTIMBRES (NUMREG, UUIDTIMBRE, MONTO, SERIE, FOLIO, RFCEMISOR, RFCRECEPTOR, ORDEN, FECHA, TIPOCOMPROBANTE, TIPOCAMBIO, VERSIONCFDI, MONEDA)
                             VALUES ( (SELECT CTUUIDCOMP FROM CONTROL) + 1 , '$uuid', $pol->IMPORTE, '$pol->SERIE', '$pol->FOLIO', '$pol->RFCE', '$pol->CLIENTE', $a->NUM_PART, '$pol->FECHA', 1,  $pol->TIPOCAMBIO, '3.3', '$pol->MONEDA')";       
                $r=$this->grabaBD();
                if($r == 1 ){
                    $this->query="UPDATE CONTROL SET CTUUIDCOMP = CTUUIDCOMP + 1";
                    $this->queryActualiza();
                    $this->query="UPDATE AUXILIAR$eje a set a.IDUUID = (SELECT CTUUIDCOMP FROM CONTROL) where a.NUM_POLIZ='$folio' and a.periodo = $periodo and a.ejercicio = $ejercicio and a.NUM_PART = $a->NUM_PART";
                    $this->queryActualiza();
                }
            }
        }
       return;
    }

    function traePoliza($documento){
        foreach ($documento as $key) {
            $eje=substr($key->EJERCICIO,2);
            $periodo = $key->PERIODO;
            $tipo = $key->TIPO; 
            $poliza = $key->POLIZA; 
            $this->query="SELECT * FROM AUXILIAR$eje where NUM_POLIZ = '$poliza' and periodo = $periodo and TIPO_POLI = '$tipo'";
            $res=$this->EjecutaQuerySimple();
            while($tsArray=ibase_fetch_object($res)){
                $data[]=$tsArray;
            }
        }
        return $data;
    }

    function insertaPoliza($datosCliente, $cuenta, $datosBanco, $datosCuenta){

        $mes = $datosCliente->MES;
        $anio = $datosCliente->ANIO;
        $fechaelab = $datosCliente->FECHAELAB;
        $concepto = $datosCliente->CVE_DOC.' - '.$datosCliente->CVE_CLPV.' - '.$datosCliente->NOMBRE.' - '.$datosCliente->FECHAELAB.' - '.$datosCliente->IMPORTE;
        $importe = $datosCliente->IMPORTE;
        $iva = $datosCliente->IMP_TOT4;
        $subtotal = $importe - $iva;
        $rfc = $datosCliente->RFC;
        $fechadoc = $datosCliente->FECHA_DOC;

        $this->query="SELECT iif(MAX(NUM_POLIZ) is null, 0 , MAX(NUM_POLIZ)) as FolioA FROM POLIZAS17 WHERE TIPO_POLI = 'Dr' AND PERIODO = $mes and Ejercicio = $anio";
        $res = $this->QueryObtieneDatosN();
        $rowPoliza = ibase_fetch_object($res);
        $folio=$rowPoliza->FOLIOA + 1;
            //echo $this->query;
            //echo 'Numero del folio: '.$rowPoliza->FOLIOA;
            //echo 'Tamaño del foliio: '.strlen($folio);
            //break;
            if(strlen($folio) == 1){
                $folio = '    '.$folio;
            }elseif (strlen($folio) == 2 ){
                $folio = '   '.$folio;
            }elseif (strlen($folio) == 3 ) {
                $folio = '  '.$folio;
            }elseif (strlen($folio) == 4) {
                $folio = ' '.$folio;
            }elseif (strlen($folio) == 5) {
                $folio = $folio;
            }

        $this->query = "INSERT INTO POLIZAS17 VALUES ('Dr', '$folio', $mes, $anio, '$fechaelab', '$concepto', 0, '0', 'N',0,1,0,'Pegaso','0','0',0)";
        $insPol = $this->EjecutaQuerySimple();

        //echo $this->query;
        //break;
        /// Inserta poliza de Dr segun el mes de la venta.

        $this->query = "INSERT INTO AUXILIAR17 VALUES ('Dr', '$folio',1, $mes, $anio, '410000100100000000003', '$fechaelab', ('Ventas Nacionales, '||'$datosCliente->CVE_DOC'), 'H', $subtotal, 0, 1, 0, 1, 0, 0, 0, 0) ";
        $rs=$this->EjecutaQuerySimple();
        $this->query = "INSERT INTO AUXILIAR17 VALUES ('Dr', '$folio',2, $mes, $anio, '218100100000000000002', '$fechaelab', ('Iva por Acreeditar, '||'$datosCliente->CVE_DOC '), 'H', $iva, 0, 1, 0, 1, 0, 0, 0, 0) ";
        $rs=$this->EjecutaQuerySimple();
        $this->query = "INSERT INTO AUXILIAR17 VALUES ('Dr', '$folio',3, $mes, $anio, '$cuenta', '$fechaelab', ('Clientes'||' - '||'$datosCliente->NOMBRE'||', '||'$datosCliente->CVE_DOC'), 'D', $importe, 0, 1, 0, 1, 0, 0, 0, 0) ";
        $rs=$this->EjecutaQuerySimple();

        //// Inserta la poliza de Ig segun el mes del Pago.
        ###########################################
        ################  Poliza de Ig ############
        ###########################################
        //averiguar ultimo folio de Ig 
        $fechaPago = $datosBanco->FECHA_RECEP;
        $mesPago = substr($fechaPago, 5,2);
        $anioPago = substr($fechaPago, 0,4);
        $mesPago = str_replace('0','',$mesPago);
        $concepto = $datosBanco->BANCO.', '.$concepto;

        //echo $concepto;

        $this->query="SELECT iif(MAX(NUM_POLIZ) is null, 0 , MAX(NUM_POLIZ)) as FolioIg FROM POLIZAS17 WHERE TIPO_POLI = 'Ig' AND PERIODO = $mesPago and Ejercicio = $anioPago ";
        $res=$this->QueryObtieneDatosN();

        $rowIg = ibase_fetch_object($res);
        $folioIg = $rowIg->FOLIOIG + 1;

                if(strlen($folioIg) == 1){
                $folioIg = '    '.$folioIg;
                }elseif (strlen($folioIg) == 2 ){
                    $folioIg = '   '.$folioIg;
                }elseif (strlen($folioIg) == 3 ) {
                    $folioIg = '  '.$folioIg;
                }elseif (strlen($folioIg) == 4) {
                    $folioIg = ' '.$folioIg;
                }elseif (strlen($folioIg) == 5) {
                    $folioIg = $folioIg;
                }        
        
        $cuentaBanco = $datosCuenta->CTA_CONTAB;
        
        $this->query = "INSERT INTO POLIZAS17 VALUES ('Ig', '$folioIg', $mesPago, $anioPago, '$fechaPago', '$concepto', 0, '0', 'N',0,1,0,'Pegaso','0','0',0)";
        $insPol = $this->EjecutaQuerySimple();
        

        $this->query="INSERT INTO AUXILIAR17 VALUES ('Ig', '$folioIg',1, $mesPago, $anioPago, '$cuentaBanco', '$fechaelab', ('Ingreso a Banco Nacionales, '||'$datosCliente->CVE_DOC'), 'D', $importe, 0, 1, 0, 1, 0, 0, 0, 0) ";
        $rs=$this->EjecutaQuerySimple();
        ##echo 'Esta es la insecion a BAncos: '.$this->query;
        $this->query="INSERT INTO AUXILIAR17 VALUES ('Ig', '$folioIg',2, $mesPago, $anioPago, '218100100000000000002', '$fechaelab', ('Ingreso a Banco Nacionales, '||'$datosCliente->CVE_DOC'), 'D', $iva, 0, 1, 0, 1, 0, 0, 0, 0) ";
        $rs=$this->EjecutaQuerySimple(); 
        $this->query="INSERT INTO AUXILIAR17 VALUES ('Ig', '$folioIg',3, $mesPago, $anioPago, '218000100000000000002', '$fechaelab', ('Ingreso a Banco Nacionales, '||'$datosCliente->CVE_DOC'), 'H', $iva, 0, 1, 0, 1, 0, 0, 0, 0) ";
        $rs=$this->EjecutaQuerySimple();
        $this->query="INSERT INTO AUXILIAR17 VALUES ('Ig', '$folioIg',4, $mesPago, $anioPago, '$cuenta', '$fechaelab', ('Abono a Clientes, '||'$datosCliente->CVE_DOC'), 'H', $importe, 0, 1, 0, 1, 0, 0, 0, 0) ";
        $rs=$this->EjecutaQuerySimple();

        ###########################################
        ######  Fin Poliza de Ingresos ############
        ###########################################

        return $rs;

    }

    function insertarPolizas($datosPolizas){
            /// Obtenemos la cuenta del proveedor.
        if(!empty($datosPolizas)){
            foreach ($datosPolizas as $data){
                $rfc2= $data->RFC;
                $mes = $data->MES;
                $anio = $data->ANIO;
                $monto = $data->CARGO;
                $fecha = $data->FE;
                $id = $data->ID;
                $concepto = $data->TIPO_BASE_PROV.', '.$fecha.', '.$data->IDENTIFICADOR.', '.$rfc2.', '.$data->NOMBRE.', $ '.$monto;
                $subtotal = $monto / 1.16;
                $iva = $monto - $subtotal;
                $tipoEgreso = $data->TIPO_BASE_PROV;
                $cuenta = $data->CTA_CONT;
                $nombreProveedor = $data->NOMBRE;
                    if($rfc2 =='rfcgenerico'){
                        //$this->query="SELECT NUM_CTA as cuenta FROM CUENTAS1602 WHERE RFC = '$data->RFC'";
                        //$rs = $this->QueryObtieneDatosN();
                        //$row=ibase_fetch_object($rs);
                        //$cuenta=$row->CUENTA;
                        $cuenta='211000100200000000003';
                    }
                    //// Obtenemos el ultimo folio y creamos el nuevo Folio de Dr.
                    $this->query="SELECT iif(MAX(NUM_POLIZ) is null, 0, MAX(NUM_POLIZ)) as folioa FROM POLIZAS17 WHERE TIPO_POLI = 'Dr' and periodo = $mes and  Ejercicio = $anio";
                    $res = $this->QueryObtieneDatosN();
                    $rowF = ibase_fetch_object($res);
                    $folioDrN = $rowF->FOLIOA + 1;
                                if(strlen($folioDrN) == 1){
                                    $folioDrN = '    '.$folioDrN;
                                }elseif (strlen($folioDrN) == 2 ){
                                    $folioDrN = '   '.$folioDrN;
                                }elseif (strlen($folioDrN) == 3 ) {
                                    $folioDrN = '  '.$folioDrN;
                                }elseif (strlen($folioDrN) == 4) {
                                    $folioDrN = ' '.$folioDrN;
                                }elseif (strlen($folioDrN) == 5) {
                                    $folioDrN = $folioDrN;
                                }
                    //// Obtenemos el ultimo folio de Egresos y creamos el nuevo folio.
                                $this->query="SELECT iif(MAX(NUM_POLIZ) is null, 0, MAX(NUM_POLIZ)) as folioEG FROM POLIZAS17 WHERE TIPO_POLI = 'Eg' and periodo = $mes and  Ejercicio = $anio";
                                $res = $this->QueryObtieneDatosN();
                                $rowF = ibase_fetch_object($res);
                                $folioEgN = $rowF->FOLIOEG + 1;
                                if(strlen($folioEgN) == 1){
                                    $folioEgN = '    '.$folioEgN;
                                }elseif (strlen($folioEgN) == 2 ){
                                    $folioEgN = '   '.$folioEgN;
                                }elseif (strlen($folioEgN) == 3 ) {
                                    $folioEgN = '  '.$folioEgN;
                                }elseif (strlen($folioEgN) == 4) {
                                    $folioEgN = ' '.$folioEgN;
                                }elseif (strlen($folioEgN) == 5) {
                                    $folioEgN = $folioEgN;
                                }

                    //// Obtenemos la cuenta del Banco
                                if($data->BANCO == 'Bancomer - 0156324495'){
                                    $cuentaBanco = '112000100600000000003';
                                }elseif ($data->BANCO == 'Banamex - 9318771457'){
                                    $cuentaBanco = '112000100100000000003';
                                }elseif ($data->BANCO == 'ScotiaBank - 044180001025870734') {
                                    $cuentaBanco = '112000100700000000003';
                                }

                ##########################################################
                ###### CREACION DE POLIZA PROVISION DE COMPRA ############
                ##########################################################
                ///// Insertamos Poliza de Dr.
                $this->query = "INSERT INTO POLIZAS17 VALUES ('Dr', '$folioDrN', $mes, $anio, '$fecha', substring('$concepto' from 1 for 119), 0, 'N', 'N',0,1,0,'Pegaso','0','0',0)";
                $insPol = $this->EjecutaQuerySimple();

                if($insPol == 0){
                     $errores[]=array('Poldr', $folioDrN, $mes, $anio, $id);
                 }else{
                        if(substr($tipoEgreso, 0,1) == 'G'){
                        $cuentaEgreso = "620000100100000000003";
                    }else{
                        $cuentaEgreso = "119000100000000000002";
                    }
                        $this->query = "INSERT INTO AUXILIAR17 VALUES ('Dr', '$folioDrN',1, $mes, $anio, '$cuentaEgreso', '$fecha', ('Almacen, '||'$data->NOMBRE'), 'D', $subtotal, 0, 1, 0, 1, 0, 0, 0, 0) ";
                        $rs=$this->EjecutaQuerySimple();
                        // Partida Impuestos
                        $this->query = "INSERT INTO AUXILIAR17 VALUES ('Dr', '$folioDrN',2, $mes, $anio, '120100100000000000002', '$fecha', ('Iva por Pagar, '||'$data->NOMBRE'), 'D', $iva, 0, 1, 0, 1, 0, 0, 0, 0) ";
                        $rs=$this->EjecutaQuerySimple();
                        // Partida del proveedor
                        $this->query = "INSERT INTO AUXILIAR17 VALUES ('Dr', '$folioDrN',3, $mes, $anio, '$cuenta', '$fecha', ('Proveedor'||' - '||'$data->NOMBRE'||', '||'$data->PROVEEDOR'), 'H', $monto, 0, 1, 0, 1, 0, 0, 0, 0) ";
                        $rs=$this->EjecutaQuerySimple();
                 }
                
                ##########################################################
                ########### CREACION DE POLIZA PAGO A PROVEEDORES ########
                ##########################################################

                /// Insertamos la poliza de Eg.

                $this->query = "INSERT INTO POLIZAS17 VALUES ('Eg', '$folioEgN', $mes, $anio, '$fecha', substring('$concepto' from 1 for 119), 0, '0', 'N',0,1,0,'Pegaso','0','0',0)";
                $insPoleg = $this->EjecutaQuerySimple();
                       if($insPoleg == 0){
                            $errores[]=array('Poleg', $folioEgN, $mes, $anio, $id);
                       }else{
                                // Partida Proveedor
                            $this->query="INSERT INTO AUXILIAR17 VALUES ('Eg', '$folioEgN',1, $mes, $anio, '$cuenta', '$fecha', ('Pago a Proveedores, '||'$data->IDENTIFICADOR'), 'D', $monto, 0, 1, 0, 1, 0, 0, 0, 0) ";
                            $rs=$this->EjecutaQuerySimple();
                            //Partida Impuestos
                            $this->query="INSERT INTO AUXILIAR17 VALUES ('Eg', '$folioEgN',2, $mes, $anio, '120100100000000000002', '$fecha', ('Egreso de Banco Nacionales, '||'$data->NOMBRE'||', '||'$rfc2'), 'H', $iva, 0, 1, 0, 1, 0, 0, 0, 0) ";
                            $rs=$this->EjecutaQuerySimple(); 
                            $this->query="INSERT INTO AUXILIAR17 VALUES ('Eg', '$folioEgN',3, $mes, $anio, '120000100000000000002', '$fecha', '$rfc2', 'D', $iva, 0, 1, 0, 1, 0, 0, 0, 0) ";
                            $rs=$this->EjecutaQuerySimple();
                            // Partida Banco
                            $this->query="INSERT INTO AUXILIAR17 VALUES ('Eg', '$folioEgN',4, $mes, $anio, '$cuentaBanco', '$fecha', ('Egreso de Banco Nacionales, '||'$data->NOMBRE'), 'H', $monto, 0, 1, 0, 1, 0, 0, 0, 0) ";
                            $rs=$this->EjecutaQuerySimple();
                       }
                ##echo 'Esta es la insecion a BAncos: '.$this->query 
                ###########################################
                ######  Fin Poliza de Ingresos ############
                ###########################################
                $folioEG = 'Eg'.$folioEgN;
                $folioDr = 'Dr'.$folioDrN;
                $polizas[]=array(1 => $id, 2 => $folioEG, 3 =>$folioDr);
            }
               // var_dump($polizas);
        }

            if(!empty($polizas)){
                        if(isset($errores)){
                            foreach ($errores as $key) {
                            echo $this->query.'<p>';
                            echo $key[0].', '.$key[1].', '.$key[2].', '.$key[3].', '.$key[4].'<p>';
                            }
                        } 
                return $polizas;    
            }
                return 'Nothing to do....';
    }

    function insertarPolizas_Dr_Ventas($datosPolizas){
                ##########################################################
                ##VALIDAMOS QUE EXISTA LA CUENTA, SI NO, LA CREAMOS ######
                ##########################################################

                foreach($datosPolizas as $dataC){
                $rfc = $dataC->RFC;
                $nombre = $dataC->NOMBRE;
                $this->query= "SELECT iif(MAX(NUM_CTA) is null, 0, max(NUM_CTA)) as valor FROM CUENTAS17 WHERE RFC = '$rfc'";
                $resultado = $this->QueryObtieneDatosN();
                $rowCuenta = ibase_fetch_object($resultado);
                $valCuenta = $rowCuenta->VALOR;
                //echo $valCuenta;
                //echo $this->query;
                //break;
                    if($valCuenta ==  0 ){
                        $this->query ="SELECT MIN(NUM_CTA) as papa, substring(MAX(num_cta) from 8 for 3) as hija FROM CUENTAS17 WHERE NUM_CTA STARTING WITH ('1150001')";
                        $rs = $this->QueryObtieneDatosN();
                        $rowCtaDet= ibase_fetch_object($rs);
                        $ctaPapa = $rowCtaDet->PAPA;
                        $ctaHija = $rowCtaDet->HIJA;
                        $nueva = $ctaHija + 1;
                            if(strlen($nueva) == 1){
                                $nueva = '00'.$nueva;
                            }elseif (strlen($nueva) == 2){
                                $nueva = '0'.$nueva;
                            }elseif (strlen($nueva) == 3){
                                $nueva = $nueva;
                            }
                            $cuentaNueva = substr($ctaPapa, 0, 7).$nueva.'00000000003';
                        $this->query = "INSERT INTO CUENTAS17 VALUES ('$cuentaNueva', 'A', 'D', substring('$nombre' from 1 for 40), 'N', 1,'N','$ctaPapa', '115000000000000000001', 3, '', 0, '$rfc', '', 0,0, 0, '','N', 0, '', 0,'' )";
                        $rs= $this->EjecutaQuerySimple();
                        //echo $this->query;
                        $this->query = "INSERT INTO SALDOS17 (num_cta, Ejercicio) VALUES ('$cuentaNueva', 2016)";
                        $rs = $this->EjecutaQuerySimple();

                        $valCuenta = $cuentaNueva;
                }
            }
                ##########################################################
                ############ FIN DE LA VALIDACION DE LA CUENTA ###########
                ##########################################################

                ##########################################################
                ############ INSERTAMOS LA POLIZA DE DR  #################
                ##########################################################

                 foreach($datosPolizas as $dataP){
                       $this->query = "SELECT NUM_CTA FROM CUENTAS17 WHERE rfc = '$dataP->RFC'";
                       $rs=$this->QueryObtieneDatosN();
                       $row=ibase_fetch_object($rs);

                       if(!empty($row)){
                            $cuentaCliente = $row->NUM_CTA;
                       }else{
                            $cuentaCliente = '115000100100000000003'; /// Cuenta de cliente generico.
                       }

                        $id = $dataP->ID;
                        $mes = $dataP->MES;
                        $anio = $dataP->ANIO;
                        $fechaelab = $dataP->FECHAELAB;
                        $concepto = $dataP->DOCUMENTO.' - '.$dataP->CVE_CLPV.' - '.$dataP->NOMBRE.' - '.$dataP->FECHAELAB.' - '.$dataP->IMPORTE.', '.$dataP->MONTO_APLICADO;
                        $importe = $dataP->IMPORTE;
                        $iva = $dataP->IMP_TOT4;
                        $subtotal = $importe - $iva;
                        $rfc = $dataP->RFC;
                        $fechadoc = $dataP->FECHA_DOC;

                        $a="SELECT iif(MAX(NUM_POLIZ) is null, 0 , MAX(NUM_POLIZ)) as FolioA FROM POLIZAS17 WHERE TIPO_POLI = 'Dr' AND PERIODO = $mes and Ejercicio = $anio";
                        //$res = $this->QueryObtieneDatosN();
                        $this->query=$a;
                        $res=$this->QueryObtieneDatosN();
                        $rowPoliza = ibase_fetch_object($res);
                        $folioA = (int)$rowPoliza->FOLIOA;
                        $folio = $folioA + 1;

                            if(strlen($folio) == 1){
                                $folio = '    '.$folio;
                            }elseif (strlen($folio) == 2 ){
                                $folio = '   '.$folio;
                            }elseif (strlen($folio) == 3 ) {
                                $folio = '  '.$folio;
                            }elseif (strlen($folio) == 4) {
                                $folio = ' '.$folio;
                            }elseif (strlen($folio) == 5) {
                                $folio = $folio;
                            }

                        $this->query = "INSERT INTO POLIZAS17 VALUES ('Dr', '$folio', $mes, $anio, '$fechaelab', substring('$concepto' from 1 for 119), 0, '0', 'N',0,1,0,'Pegaso','0','0',0)";
                        $insPol = $this->EjecutaQuerySimple();
                        if($insPol != 1){
                            echo 'Poliza: '.$this->query;
                            echo 'Valor del Folio A: '.$folioA;
                            echo 'Valor del Folio: '.$folio;
                            echo 'Error: '.$this->query;
                            echo $a;
                            break;
                        }
                        //break;
                        /// Inserta poliza de Dr segun el mes de la venta.
                        $this->query = "INSERT INTO AUXILIAR17 VALUES ('Dr', '$folio',1, $mes, $anio, '410000100100000000003', '$fechaelab', ('Ventas Nacionales, '||'$dataP->DOCUMENTO'), 'H', $subtotal, 0, 1, 0, 1, 0, 0, 0, 0) ";
                        $rs=$this->EjecutaQuerySimple();
                        if($rs != 1){
                            echo 'Valor del Folio A: '.$folioA;
                            echo 'Valor del Folio: '.$folio;
                            echo 'Error: '.$this->query;
                            echo $a;
                            break;
                        }

                        $this->query = "INSERT INTO AUXILIAR17 VALUES ('Dr', '$folio',2, $mes, $anio, '218100100000000000002', '$fechaelab', ('Iva por Acreeditar, '||'$dataP->DOCUMENTO'), 'H', $iva, 0, 1, 0, 1, 0, 0, 0, 0) ";
                        $rs=$this->EjecutaQuerySimple();

                        if($rs != 1){
                            echo 'Error: '.$this->query;
                        }

                        $this->query = "INSERT INTO AUXILIAR17 VALUES ('Dr', '$folio',3, $mes, $anio, '$cuentaCliente', '$fechaelab', ('Clientes'||' - '||'$dataP->NOMBRE'||', '||'$dataP->DOCUMENTO'), 'D', $importe, 0, 1, 0, 1, 0, 0, 0, 0) ";
                        $rs=$this->EjecutaQuerySimple();


                        if($rs != 1){
                            echo 'Error: '.$this->query;
                        }

                        $polizasAplicaciones[]=array(0 => $id,1 => $folio);
                 }       

                return $polizasAplicaciones; 
            }

        function insertarPolizas_Ig_Ventas($datosPolizas){
                ##########################################################
                ##VALIDAMOS QUE EXISTA LA CUENTA, SI NO, LA CREAMOS ######
                ##########################################################
                foreach($datosPolizas as $dataC){
                $rfc = $dataC->RFC;
                $nombre = $dataC->NOMBRE;

                $this->query= "SELECT iif(MAX(NUM_CTA) is null, 0, max(NUM_CTA)) as valor FROM CUENTAS17 WHERE RFC = '$rfc'";
                $resultado = $this->QueryObtieneDatosN();
                $rowCuenta = ibase_fetch_object($resultado);
                $valCuenta = $rowCuenta->VALOR;
                //echo $valCuenta;
                //echo $this->query;
                //break;
                    if($valCuenta ==  0 ){
                        $this->query ="SELECT MIN(NUM_CTA) as papa, substring(MAX(num_cta) from 8 for 3) as hija FROM CUENTAS17 WHERE NUM_CTA STARTING WITH ('1150001')";
                        $rs = $this->QueryObtieneDatosN();
                        $rowCtaDet= ibase_fetch_object($rs);
                        $ctaPapa = $rowCtaDet->PAPA;
                        $ctaHija = $rowCtaDet->HIJA;
                        $nueva = $ctaHija + 1;
                            if(strlen($nueva) == 1){
                                $nueva = '00'.$nueva;
                            }elseif (strlen($nueva) == 2){
                                $nueva = '0'.$nueva;
                            }elseif (strlen($nueva) == 3){
                                $nueva = $nueva;
                            }
                            $cuentaNueva = substr($ctaPapa, 0, 7).$nueva.'00000000003';
                        $this->query = "INSERT INTO CUENTAS17 VALUES ('$cuentaNueva', 'A', 'D', substring('$nombre' from 1 for 40), 'N', 1,'N','$ctaPapa', '115000000000000000001', 3, '', 0, '$rfc', '', 0,0, 0, '','N', 0, '', 0,'' )";
                        $rs= $this->EjecutaQuerySimple();
                        //echo $this->query;
                        $this->query = "INSERT INTO SALDOS17 (num_cta, Ejercicio) VALUES ('$cuentaNueva', 2016)";
                        $rs = $this->EjecutaQuerySimple();

                        $valCuenta = $cuentaNueva;
                }
            }
                ##########################################################
                ############ FIN DE LA VALIDACION DE LA CUENTA ###########
                ##########################################################


                ##########################################################
                ############ INSERTAMOS LA POLIZA DE DR  #################
                ##########################################################

                 foreach($datosPolizas as $dataP){
                       $this->query = "SELECT NUM_CTA FROM CUENTAS17 WHERE rfc = '$dataP->RFC'";
                       $rs=$this->QueryObtieneDatosN();
                       $row=ibase_fetch_object($rs);

                       if(!empty($row)){
                            $cuentaCliente = $row->NUM_CTA;
                       }else{
                            $cuentaCliente = '115000100100000000003'; /// Cuenta de cliente generico.
                       }

                        $id = $dataP->ID;
                        $mes = $dataP->MES;
                        $anio = $dataP->ANIO;
                        $fechaelab = $dataP->FECHAELAB;
                        $concepto = $dataP->DOCUMENTO.' - '.$dataP->CVE_CLPV.' - '.$dataP->NOMBRE.' - '.$dataP->FECHAELAB.' - '.$dataP->IMPORTE.', '.$dataP->MONTO_APLICADO;
                        $importe = $dataP->IMPORTE;
                        $iva = $dataP->IMP_TOT4;
                        $subtotal = $importe - $iva;
                        $rfc = $dataP->RFC;
                        $fechadoc = $dataP->FECHA_DOC;

                        $a="SELECT iif(MAX(NUM_POLIZ) is null, 0 , MAX(NUM_POLIZ)) as FolioA FROM POLIZAS17 WHERE TIPO_POLI = 'Dr' AND PERIODO = $mes and Ejercicio = $anio";
                        //$res = $this->QueryObtieneDatosN();
                        $this->query=$a;
                        $res=$this->QueryObtieneDatosN();
                        $rowPoliza = ibase_fetch_object($res);
                        $folioA = (int)$rowPoliza->FOLIOA;
                        $folio = $folioA + 1;

                            if(strlen($folio) == 1){
                                $folio = '    '.$folio;
                            }elseif (strlen($folio) == 2 ){
                                $folio = '   '.$folio;
                            }elseif (strlen($folio) == 3 ) {
                                $folio = '  '.$folio;
                            }elseif (strlen($folio) == 4) {
                                $folio = ' '.$folio;
                            }elseif (strlen($folio) == 5) {
                                $folio = $folio;
                            }

                        $this->query = "INSERT INTO POLIZAS17 VALUES ('Dr', '$folio', $mes, $anio, '$fechaelab', substring('$concepto' from 1 for 119), 0, '0', 'N',0,1,0,'Pegaso','0','0',0)";
                        $insPol = $this->EjecutaQuerySimple();
                        if($insPol != 1){
                            echo 'Poliza: '.$this->query;
                            echo 'Valor del Folio A: '.$folioA;
                            echo 'Valor del Folio: '.$folio;
                            echo 'Error: '.$this->query;
                            echo $a;
                            break;
                        }
                        //break;
                        /// Inserta poliza de Dr segun el mes de la venta.
                        $this->query = "INSERT INTO AUXILIAR17 VALUES ('Dr', '$folio',1, $mes, $anio, '410000100100000000003', '$fechaelab', ('Ventas Nacionales, '||'$dataP->DOCUMENTO'), 'H', $subtotal, 0, 1, 0, 1, 0, 0, 0, 0) ";
                        $rs=$this->EjecutaQuerySimple();
                        if($rs != 1){
                            echo 'Valor del Folio A: '.$folioA;
                            echo 'Valor del Folio: '.$folio;
                            echo 'Error: '.$this->query;
                            echo $a;
                            break;
                        }

                        $this->query = "INSERT INTO AUXILIAR17 VALUES ('Dr', '$folio',2, $mes, $anio, '218100100000000000002', '$fechaelab', ('Iva por Acreeditar, '||'$dataP->DOCUMENTO'), 'H', $iva, 0, 1, 0, 1, 0, 0, 0, 0) ";
                        $rs=$this->EjecutaQuerySimple();

                        if($rs != 1){
                            echo 'Error: '.$this->query;
                        }

                        $this->query = "INSERT INTO AUXILIAR17 VALUES ('Dr', '$folio',3, $mes, $anio, '$cuentaCliente', '$fechaelab', ('Clientes'||' - '||'$dataP->NOMBRE'||', '||'$dataP->DOCUMENTO'), 'D', $importe, 0, 1, 0, 1, 0, 0, 0, 0) ";
                        $rs=$this->EjecutaQuerySimple();


                        if($rs != 1){
                            echo 'Error: '.$this->query;
                        }

                        $polizasAplicaciones[]=array(0 => $id,1 => $folio);
                 }       

                return $polizasAplicaciones; 
            }

    function tabla_temp_aplicaciones($aplicaciones, $pagos){
        $this->query="DELETE from temp_table";
        $rs=$this->grabaBD();

        if(count($aplicaciones) > 0){
             $rfcs= 0;
            foreach ($aplicaciones as $key){
                $ida= $key->ID;
                $idp = $key->IDPAGO;
                $montoAplicado = round($key->MONTO_APLICADO,2);
                $contabilizado = $key->CONTABILIZADO;
                $rfc =trim($key->RFC);
                $docf = $key->DOCUMENTO;
                //$rfc = str_replace(' ','',$rfc);
                $this->query="SELECT NUM_CTA FROM CUENTAS18 WHERE trim(RFC) =trim('$rfc')";
                $rs=$this->EjecutaQuerySimple();
                $row=ibase_fetch_object($rs);
               
                if($row){
                    $cuenta = $row->NUM_CTA;    
                        $rfcs=$rfcs + 1;
                }else{
                    echo $rfc.'<br/>';
                    //echo $this->query.'<br/>';
                }
                $this->query="INSERT INTO TEMP_TABLE VALUES($ida, $idp, $montoAplicado, '$rfc', '$cuenta', 'No','$docf')";
                //$res=$this->EjecutaQuerySimple();
                    if($this->EjecutaQuerySimple()==false){
                        echo $this->query.'<br/>';
                    }
                }
        }
        //exit('Aplicaciones: '.count($aplicaciones).' rfcs: '. $rfcs);

        foreach($pagos as $data){
            $idp = $data->ID;
            $cuentaBanco = $data->CTA_CONTAB;
            $montoDoc = round($data->MONTO,2);
            $mes = $data->MES;
            $anio = $data->ANIO;
            $concepto = 'Ingreso a Bancos '.$data->BANCO.', folio: '.$idp.', Monto: '.$montoDoc;
            $subtotal = round($montoDoc / 1.16,2);
            $iva = round($montoDoc - $subtotal,2);
            $fechaR = $data->FECHA_RECEP;
            
            $this->query= "SELECT * from TEMP_TABLE WHERE IDP = $idp";
            $rs=$this->EjecutaQuerySimple();
            while($tsArray = ibase_fetch_object($rs)){
                  $partida[]=$tsArray;
            }

            /// Obtenemos el ultimo folio de las polizas de Ingreso Ig con base al mes y al año.    
                ############# Algoritmo para calcular el Nuevo Folio.
                    $a="SELECT iif(MAX(NUM_POLIZ) is null, 0 , MAX(NUM_POLIZ)) as FolioA FROM POLIZAS18 WHERE TIPO_POLI = 'Ig' AND PERIODO = $mes and Ejercicio = $anio";
                        //$res = $this->QueryObtieneDatosN();
                        $this->query=$a;
                        $res=$this->QueryObtieneDatosN();
                        $rowPoliza = ibase_fetch_object($res);
                        $folioA = (int)$rowPoliza->FOLIOA;
                        $folioN = $folioA + 1;
                            if(strlen($folioN) == 1){
                                $folioN = '    '.$folioN;
                            }elseif (strlen($folioN) == 2 ){
                                $folioN = '   '.$folioN;
                            }elseif (strlen($folioN) == 3 ) {
                                $folioN = '  '.$folioN;
                            }elseif (strlen($folioN) == 4) {
                                $folioN = ' '.$folioN;
                            }elseif (strlen($folioN) == 5) {
                                $folioN = $folioN;
                            }
            /// Insercion de la poliza 
            $this->query ="INSERT INTO POLIZAS18 VALUES ('Ig','$folioN', $mes, $anio, '$fechaR', substring('$concepto' from 1 for 119),0,'0','N',0,1,0,'Pegaso', '0', '0',0)";
            $rs=$this->EjecutaQuerySimple();
            if($rs == 0){
                /// arreglo para cachar los errores.

            }else{
                        /// Insertamos primer partida del Banco             
                        $this->query ="INSERT INTO AUXILIAR18 VALUES ('Ig','$folioN',1,$mes, $anio,'$cuentaBanco','$fechaR' ,substring('$concepto' from 1 for 119), 'D' ,$montoDoc,0,1,0,1,0,0,0,0, 'Pegaso' )";
                        $rs=$this->EjecutaQuerySimple();    
                        /// Insercion de partida de impuestos 
                        if($rs == false){
                            echo $this->query.'<p>';
                        }
                        $this->query ="INSERT INTO AUXILIAR18 VALUES ('Ig','$folioN',2,$mes, $anio, '218100100010000000003','$fechaR',substring('$concepto' from 1 for 119), 'D', $iva, 0,1,0,1,0,0,0,0, 'Pegaso')";
                        $rs=$this->EjecutaQuerySimple();
                        if($rs == false){
                            echo $this->query.'<p>';
                        }
                        $this->query ="INSERT INTO AUXILIAR18 VALUES ('Ig','$folioN',3,$mes, $anio, '218000100010000000003','$fechaR',substring('$concepto' from 1 for 119), 'H', $iva, 0,1,0,1,0,0,0,0, 'Pegaso')";
                        $rs=$this->EjecutaQuerySimple();
                        }
                        if($rs == false){
                            echo $this->query.'<p>';
                        }
                        /// insertamos partidas de los clientes-
                        $part = 4;
                        $totalPago = 0;
                        if($rs == 1 ){
                            if(isset($partida)){
                                foreach ($partida as $key2){
                                    $ida = $key2->IDA;
                                    $cuentaCliente = $key2->CUENTA_CONTABLE;
                                    $monto = $key2->MONTO_APLICADO;
                                    $docf = $key2->DOCUMENTO;
                                    /// Insercion de la poliza;
                                    $this->query ="INSERT INTO AUXILIAR18 VALUES('Ig','$folioN',$part, $mes, $anio,'$cuentaCliente','$fechaR', (substring('$concepto' from 1 for 80)||', '||'$ida'||', Fact:'||'$docf'), 'H' ,$monto, 0,1,0,1,0,0,0,0, 'Pegaso')";
                                    $res=$this->EjecutaQuerySimple();
                                    if($rs == false){
                                       echo $this->query.'<p>';
                                    }
                                    $part = $part + 1;
                                    $totalPago = $totalPago + $monto;
                                    $actualizacion[] = array(0,$ida,$folioN);
                                }
                                if($totalPago != $montoDoc){
                                    $saldo = $montoDoc - $totalPago;
                                    if($saldo > 0){
                                            $this->query="INSERT INTO AUXILIAR18 VALUES('Ig','$folioN',$part, $mes, $anio,'720000600030000000003','$fechaR', (substring('$concepto' from 1 for 100)||', '||'N/A'), 'H' ,$saldo, 0,1,0,1,0,0,0,0, 'Pegaso')";
                                            $res=$this->EjecutaQuerySimple();
                                            if($rs == false){
                            echo $this->query.'<p>';
                        }
                                            echo 'El Pago con el ID: '.$idp.' con el monto '.$montoDoc.', solo tienen aplicaciones por '.$totalPago.' con una diferencia de '.$saldo.'.<p>';
                                            /// gasto financiero cuando es menor la aplicacion 7200-005-000
                                    }else{
                                            $this->query="INSERT INTO AUXILIAR18 VALUES('Ig','$folioN',$part, $mes, $anio,'730000100020000000003','$fechaR', (substring('$concepto' from 1 for 100)||', '||'N/A'), 'D' ,$saldo, 0,1,0,1,0,0,0,0, 'Pegaso')";
                                            $res=$this->EjecutaQuerySimple();
                         if($rs == false){
                            echo $this->query.'<p>';
                        }
                                            echo 'El Pago con el ID: '.$idp.' con el monto '.$montoDoc.', solo tienen aplicaciones por '.$totalPago.' con una diferencia de '.$saldo.'.<p>';
                                            ////  a la 7300-0002-000    
                                    }
                                }
                                unset($partida);
                            }else{
                                    $saldo = $montoDoc - $totalPago;
                                    $this->query="INSERT INTO AUXILIAR18 VALUES('Ig','$folioN',$part, $mes, $anio,'115000200010000000003','$fechaR', (substring('$concepto' from 1 for 100)||', '||'N/A'), 'H' ,$saldo, 0,1,0,1,0,0,0,0, 'Pegaso')";
                                    $res=$this->EjecutaQuerySimple();
                         if($rs == false){
                            echo $this->query.'<p>';
                        }
                                    echo 'El Pago con el ID: '.$idp.' con el monto '.$montoDoc.', solo tienen aplicaciones por '.$totalPago.' con una diferencia de '.$saldo.'.<p>';
                                            /// gasto financiero cuando es menor la aplicacion 7200-005-000
                            }              
            }
            
            $actualizacion[]=array($idp,0,$folioN);
        }
          foreach ($actualizacion as $key){
              $idp=$key[0];
              $ida=$key[1];
              $poliza = $key[2];
              echo 'Afectacion de poliza: '.$poliza.', pago: '.$idp.', aplicacion: '.$ida.'<p>';
          }
         //exit(print_r($actualizacion));
        return $actualizacion;
    }


    function crear_cuentas_clientes($rfc){

        var_dump($rfc);
        foreach ($rfc as $datosCliente) {
             $rfc = $datosCliente->RFC;
             $nombre = $datosCliente->NOMBRE;
             $cuentaCliente = $datosCliente->CTA_CONT;

                if($cuentaCliente == '0'){
            
                        $this->query= "SELECT iif(MAX(NUM_CTA) is null, 0, max(NUM_CTA)) as valor FROM CUENTAS17 WHERE RFC = '$rfc'";
                        $resultado = $this->QueryObtieneDatosN();
                        $rowCuenta = ibase_fetch_object($resultado);
                        $valCuenta = $rowCuenta->VALOR;
                        $cuentaCliente=$valCuenta;
                    
                        if($valCuenta ==  0 ){
                            $this->query ="SELECT MIN(NUM_CTA) as papa, substring(MAX(num_cta) from 8 for 3) as hija FROM CUENTAS17 WHERE NUM_CTA STARTING WITH ('1150001')";
                            $rs = $this->QueryObtieneDatosN();
                            $rowCtaDet= ibase_fetch_object($rs);
                            $ctaPapa = $rowCtaDet->PAPA;
                            $ctaHija = $rowCtaDet->HIJA;
                            $nueva = $ctaHija + 1;
                                if(strlen($nueva) == 1){
                                    $nueva = '00'.$nueva;
                                }elseif (strlen($nueva) == 2){
                                    $nueva = '0'.$nueva;
                                }elseif (strlen($nueva) == 3){
                                    $nueva = $nueva;
                                }
                                $cuentaNueva = substr($ctaPapa, 0, 7).$nueva.'00000000003';

                            $this->query = "INSERT INTO CUENTAS17 VALUES ('$cuentaNueva', 'A', 'D', substring('$datosCliente->NOMBRE' from 1 for 40), 'N', 1,'N','$ctaPapa', '115000000000000000001', 3, '', 0, '$rfc', '', 0,0, 0, '','N', 0, '', 0,'' )";
                            $rs= $this->EjecutaQuerySimple();
                            //echo $this->query;
                            $this->query = "INSERT INTO SALDOS17 (num_cta, Ejercicio) VALUES ('$cuentaNueva', 2016)";
                            $rs = $this->EjecutaQuerySimple();
                            $valCuenta = $cuentaNueva;
                            $cuentaCliente =$valCuenta;
                            $actualiza[]=array($rfc, $cuentaCliente);
                        }
                        $actualiza[]=array($rfc, $cuentaCliente);
                    }       
            }
            return $actualiza;
    }


    function crea_cuentas_proveedores($rfc){
        foreach ($rfc as $datosProveedor) {
             $rfc = $datosProveedor->RFC;
             $nombre = $datosProveedor->NOMBRE;
             $cuentaProveedor = $datosProveedor->CTA_CONT;
                if($cuentaProveedor == '0'){
                        $this->query= "SELECT iif(MAX(NUM_CTA) is null, 0, max(NUM_CTA)) as valor FROM CUENTAS17 WHERE RFC = '$rfc'";
                        $resultado = $this->QueryObtieneDatosN();
                        $rowCuenta = ibase_fetch_object($resultado);
                        $valCuenta = $rowCuenta->VALOR;
                        $cuentaProveedor=$valCuenta;
                            if($valCuenta == 0 ){
                                $this->query ="SELECT MIN(NUM_CTA) as papa, substring(MAX(num_cta) from 8 for 3) as hija FROM CUENTAS17 WHERE NUM_CTA STARTING WITH ('2110002')";
                                $rs = $this->QueryObtieneDatosN();
                                $rowCtaDet= ibase_fetch_object($rs);
                                $ctaPapa = $rowCtaDet->PAPA;
                                $ctaHija = $rowCtaDet->HIJA;
                                $nueva = $ctaHija + 1;
                                    if(strlen($nueva) == 1){
                                        $nueva = '00'.$nueva;
                                    }elseif (strlen($nueva) == 2){
                                        $nueva = '0'.$nueva;
                                    }elseif (strlen($nueva) == 3){
                                        $nueva = $nueva;
                                    }
                                    $cuentaNueva = substr($ctaPapa, 0, 7).$nueva.'00000000003';

                                $this->query = "INSERT INTO CUENTAS17 VALUES ('$cuentaNueva', 'A', 'D', substring('$datosProveedor->NOMBRE' from 1 for 40), 'N', 1,'N','$ctaPapa', '211000000000000000001', 3, '', 0, '$rfc', '', 0,0, 0, '','N', 0, '', 0,'' )";
                                $rs= $this->EjecutaQuerySimple();
                                //echo $this->query.'<p>';
                                $this->query = "INSERT INTO SALDOS17 (num_cta, Ejercicio) VALUES ('$cuentaNueva', 2017)";
                                $rs = $this->EjecutaQuerySimple();
                                $valCuenta = $cuentaNueva;
                                $cuentaCliente =$valCuenta;
                                $actualiza[]=array($rfc, $cuentaNueva);
                            }
                        $actualiza[]=array($rfc, $cuentaProveedor);
                    }       
            }
            return $actualiza;
    }

    function contabiliza_ventas($ventas){

        foreach ($ventas as $datosCliente){
            $cuentaCliente =$datosCliente->CTA_CONT;
            $mes = $datosCliente->MES;
            $anio = $datosCliente->ANIO;
            $fechaelab = $datosCliente->FECHAELAB;
            $concepto = $datosCliente->CVE_DOC.' - '.$datosCliente->CVE_CLPV.' - '.$datosCliente->NOMBRE.' - '.$datosCliente->FECHAELAB.' - '.$datosCliente->IMPORTE;
            $importe = $datosCliente->IMPORTE;
            $iva = $datosCliente->IMP_TOT4;
            $subtotal = $importe - $iva;
            $rfc = trim($datosCliente->RFC);
            $fechadoc = $datosCliente->FECHA_DOC;
            $docf = $datosCliente->CVE_DOC;
            $rfcs= 0;
            $cuentaCliente='';

            $this->query="SELECT NUM_CTA FROM CUENTAS18 WHERE trim(RFC) =trim('$rfc') and NUM_CTA STARTING WITH ('1150001')";
            $rs=$this->EjecutaQuerySimple();
            $row=ibase_fetch_object($rs);
            
            if($row){
                $cuentaCliente = $row->NUM_CTA;    
                $rfcs=$rfcs + 1;
            }else{
                echo $rfc.'<br/>';
            }
            //exit(print_r('--'.$cuentaCliente.'--'));
            if($cuentaCliente  != ''){
                    $this->query="SELECT iif(MAX(NUM_POLIZ) is null, 0 , MAX(NUM_POLIZ)) as FolioA FROM POLIZAS18 WHERE TIPO_POLI = 'Dr' AND PERIODO = $mes and Ejercicio = $anio";
                    $res = $this->QueryObtieneDatosN();
                    $rowPoliza = ibase_fetch_object($res);
                    $folio=$rowPoliza->FOLIOA + 1;
                        if(strlen($folio) == 1){
                            $folio = '    '.$folio;
                        }elseif (strlen($folio) == 2 ){
                            $folio = '   '.$folio;
                        }elseif (strlen($folio) == 3 ) {
                            $folio = '  '.$folio;
                        }elseif (strlen($folio) == 4) {
                            $folio = ' '.$folio;
                        }elseif (strlen($folio) == 5) {
                            $folio = $folio;
                        }
                    $this->query = "INSERT INTO POLIZAS18 VALUES ('Dr', '$folio', $mes, $anio, '$fechaelab', substring('$concepto' from 1 for 119), 0, '0', 'N',0,1,0,'Pegaso','0','0',0)";
                    $insPol = $this->EjecutaQuerySimple();
                    if($insPol==0){
                        $errores[]=array('Pol', $folio, $mes, $anio, $docf);
                    }else{
                        $this->query = "INSERT INTO AUXILIAR18 VALUES ('Dr', '$folio',1, $mes, $anio, '410000100010000000003', '$fechaelab', ('Ventas Nacionales, '||'$datosCliente->CVE_DOC'), 'H', $subtotal, 0, 1, 0, 1, 0, 0, 0, 0, 'Pegaso') ";
                        $res=$this->EjecutaQuerySimple();
                        if($res == 0){
                            $errores[]=array('AUX1', $folio, $mes, $anio, $docf);
                        }
                        $this->query = "INSERT INTO AUXILIAR18 VALUES ('Dr', '$folio',2, $mes, $anio, '218100100010000000003', '$fechaelab', ('Iva por Acreeditar, '||'$datosCliente->CVE_DOC '), 'H', $iva, 0, 1, 0, 1, 0, 0, 0, 0, 'Pegaso') ";
                        $result=$this->EjecutaQuerySimple();
                        if($result==0){
                            $errores[]=array('AUX2', $folio, $mes, $anio, $docf);
                        }
                        $this->query = "INSERT INTO AUXILIAR18 VALUES ('Dr', '$folio',3, $mes, $anio, '$cuentaCliente', '$fechaelab', ('Clientes'||' - '||'$datosCliente->NOMBRE'||', '||'$datosCliente->CVE_DOC'), 'D', $importe, 0, 1, 0, 1, 0, 0, 0, 0, 'Pegaso') ";
                        $resultado=$this->EjecutaQuerySimple();
                        if($resultado == 0){
                            $errores[]=array('AUX3', $folio, $mes, $anio, $docf);
                        }
                         $actualizacion[]=array($folio, $docf, $rfc, $cuentaCliente);
                         //// ACTUALIZAMOS LA TABLA DE LOS FOLIO 
                        if($mes < 10 ){
                            $mes = '0'.$mes;
                        }
                        $campo = "FOLIO".$mes;
                        $this->query="UPDATE FOLIOS SET $campo = $folio where TIPPOL = 'Dr' and Ejercicio = $anio";
                        $this->EjecutaQuerySimple();
                    }
            }  
                
            if(isset($errores)){
                foreach ($errores as $key) {
                    echo $this->query.'<p>';
                    echo $key[0].', '.$key[1].', '.$key[2].', '.$key[3].', '.$key[4].'<p>';
                }
            }
        }        
                      
        return $actualizacion;
    }

    function contabiliza_NC($NC){
        foreach ($NC as $datosCliente){
            $cuentaCliente =$datosCliente->CTA_CONT;
            $mes = $datosCliente->MES;
            $anio = $datosCliente->ANIO;
            $fechaelab = $datosCliente->FECHAELAB;
            $concepto = $datosCliente->CVE_DOC.' - '.$datosCliente->CVE_CLPV.' - '.$datosCliente->NOMBRE.' - '.$datosCliente->FECHAELAB.' - '.$datosCliente->IMPORTE;
            $importe = $datosCliente->IMPORTE;
            $iva = $datosCliente->IMP_TOT4;
            $subtotal = $importe - $iva;
            $rfc = $datosCliente->RFC;
            $fechadoc = $datosCliente->FECHA_DOC;
            $docf = $datosCliente->CVE_DOC;
            $rfcs= 0;
            $cuentaCliente='';
            $this->query="SELECT NUM_CTA FROM CUENTAS18 WHERE trim(RFC) =trim('$rfc') and NUM_CTA STARTING WITH ('1150001')";
            $rs=$this->EjecutaQuerySimple();
            $row=ibase_fetch_object($rs);
            if($row){
                $cuentaCliente = $row->NUM_CTA;    
                $rfcs=$rfcs + 1;
            }else{
                echo $rfc.'<br/>';
            }
            if($cuentaCliente  != ''){
                $this->query="SELECT iif(MAX(NUM_POLIZ) is null, 0 , MAX(NUM_POLIZ)) as FolioA FROM POLIZAS18 WHERE TIPO_POLI = 'Dr' AND PERIODO = $mes and Ejercicio = $anio";
                $res = $this->QueryObtieneDatosN();
                $rowPoliza = ibase_fetch_object($res);
                $folio=$rowPoliza->FOLIOA + 1;
            
                    if(strlen($folio) == 1){
                        $folio = '    '.$folio;
                    }elseif (strlen($folio) == 2 ){
                        $folio = '   '.$folio;
                    }elseif (strlen($folio) == 3 ) {
                        $folio = '  '.$folio;
                    }elseif (strlen($folio) == 4) {
                        $folio = ' '.$folio;
                    }elseif (strlen($folio) == 5) {
                        $folio = $folio;
                    }
                $this->query = "INSERT INTO POLIZAS18 VALUES ('Dr', '$folio', $mes, $anio, '$fechaelab', substring('$concepto' from 1 for 119), 0, '0', 'N',0,1,0,'Pegaso','0','0',0)";
                $insPol = $this->EjecutaQuerySimple();
                if($insPol==0){
                    $errores[]=array('Pol', $folio, $mes, $anio, $docf);
                }else{
                    $this->query = "INSERT INTO AUXILIAR18 VALUES ('Dr', '$folio',1, $mes, $anio, '420000100010000000003', '$fechaelab', ('Devolucion de Venta Nacional, '||'$datosCliente->CVE_DOC'), 'D', $subtotal, 0, 1, 0, 1, 0, 0, 0, 0, 'Pegaso') ";
                    $res=$this->EjecutaQuerySimple();
                    if($res == 0){
                        $errores[]=array('AUX1', $folio, $mes, $anio, $docf);
                    }
                    $this->query = "INSERT INTO AUXILIAR18 VALUES ('Dr', '$folio',2, $mes, $anio, '218100100010000000003', '$fechaelab', ('Iva por Acreeditar, '||'$datosCliente->CVE_DOC '), 'D', $iva, 0, 1, 0, 1, 0, 0, 0, 0, 'Pegaso') ";
                    $result=$this->EjecutaQuerySimple();
                    if($result==0){
                        $errores[]=array('AUX2', $folio, $mes, $anio, $docf);
                    }
                    $this->query = "INSERT INTO AUXILIAR18 VALUES ('Dr', '$folio',3, $mes, $anio, '$cuentaCliente', '$fechaelab', ('Clientes'||' - '||'$datosCliente->NOMBRE'||', '||'$datosCliente->CVE_DOC'), 'H', $importe, 0, 1, 0, 1, 0, 0, 0, 0, 'Pegaso') ";
                    $resultado=$this->EjecutaQuerySimple();
                    if($resultado == 0){
                        $errores[]=array('AUX3', $folio, $mes, $anio, $docf);
                    }
                    $actualizacion[]=array($folio, $docf, $rfc, $cuentaCliente);    
                }       
            }  
            if(isset($errores)){
                foreach ($errores as $key) {
                    echo $this->query.'<p>';
                    echo $key[0].', '.$key[1].', '.$key[2].', '.$key[3].', '.$key[4].'<p>';
                }
            }
        }    
        return $actualizacion;
    }

    function traeCuentasContables($buscar){
        $this->query="SELECT * FROM cuentas_FTC where UPPER(cuenta) containing(UPPER('$buscar')) or UPPER(nombre) containing(UPPER('$buscar')) or UPPER(cuenta_coi) containing(UPPER('$buscar'))";
        $rs=$this->QueryDevuelveAutocompleteCuenta();
        return @$rs;
    }

    function verCuentasImp(){
        $data=array();
        $this->query="SELECT * FROM FTC_PARAM_COI WHERE TIPO ='Traslado' or TIPO ='Retencion' or TIPO ='Exento'";
        $r=$this->EjecutaQuerySimple();
        while ($tsArray=ibase_fetch_object($r)){
            $data[]=$tsArray;
        }
        return $data;
    }

    function actCuentaImp($idc, $ncta){
        $this->query="UPDATE FTC_PARAM_COI SET CUENTA_COI = (SELECT F.CUENTA FROM CUENTAS_FTC F WHERE F.CUENTA_COI = '$ncta' ), CUENTA_CONTABLE = '$ncta' WHERE ID = $idc";
        $rs=$this->queryActualiza();
        if($rs==1 ){
            return array("mensaje"=>'Se Actualio la informacion', "status"=>'ok');
        }else{
            return array("mensaje"=>'Ocurrio un error, favor de verificar la informacion', "status"=>'no');
        }
    }

    function polizaFinal($uuid, $tipo, $idp, $infoPoliza){
        /// Insertamos la poliza de egreso.
        $usuario=$_SESSION['user']->USER_LOGIN;
        $ejercicio = $infoPoliza['ejercicio'];
        $eje = substr($ejercicio, -2);
        $periodo = $infoPoliza['perido'];
        $periodo2 = str_pad($periodo, 2,'0',STR_PAD_LEFT);
        $fecha = $infoPoliza['fecha_edo'];
        $concepto = substr($tipo." ".$infoPoliza['banco'].", pago factura ".$infoPoliza['factura'].", ".$infoPoliza['proveedor']." $ ".$infoPoliza['monto'], 0, 120);
        if($tipo == 'Egreso'){
            $subTipo = 'Eg';
        }elseif($tipo == 'Ingreso'){
            $subTipo = 'Ig';
        }
        $this->query="INSERT INTO POLIZAS$eje (TIPO_POLI, NUM_POLIZ, PERIODO, EJERCICIO, FECHA_POL, CONCEP_PO, NUM_PART, LOGAUDITA, CONTABILIZ, NUMPARCUA, TIENEDOCUMENTOS, PROCCONTAB, ORIGEN, UUID, ESPOLIZAPRIVADA, UUIDOP) 
                    VALUES ('$subTipo', lpad( cast((SELECT FOLIO$periodo2 FROM FOLIOS where TIPPOL='$subTipo' AND Ejercicio = $ejercicio ) as int) + 1, 5), $periodo, $ejercicio, '$fecha', '$concepto', 4, '','N', 0, 1, 0, substring('PHP $usuario' from 1 for 15), '$uuid', 0,'') returning NUM_POLIZ";
            //echo $this->query;
        $res=$this->grabaBD();
        $row=ibase_fetch_object($res);
        $par = 0;
        if(isset($row->NUM_POLIZ)){
            echo 'Se grabo la poliz: '.$row->NUM_POLIZ;
            $this->query="UPDATE FOLIOS SET FOLIO$periodo2 = trim('$row->NUM_POLIZ') WHERE TIPPOL='$subTipo' AND Ejercicio = $ejercicio";
            $this->EjecutaQuerySimple();
            $par++;
            $ctaProv = $infoPoliza['ctaProvCoi'];
            $montoP = $infoPoliza['importe'];
            $montoI = ($infoPoliza['importe']/1.16)*.16;
            $ctaBanco = $infoPoliza['cuentaCoi'];
            $montoB = $infoPoliza['importe'];
            $conceptoA1 = substr('Pago Factura '.$infoPoliza['factura'].', '.$infoPoliza['proveedor'], 0,120);
            $conceptoA2 = substr($tipo.' '.$infoPoliza['banco'].' '.$infoPoliza['monto'], 0,120);
            $conceptoIA = substr('IVA Acreditable pagado de la factura '.$infoPoliza['factura'], 0,120);
            $conceptoIP = substr('IVA Pendiente de pago de la factura '.$infoPoliza['factura'], 0,120);
            /// Proveedor 2001-001-031  Debe
            $this->query="INSERT INTO AUXILIAR$eje (TIPO_POLI, NUM_POLIZ, NUM_PART, PERIODO, EJERCICIO, NUM_CTA, FECHA_POL, CONCEP_PO, DEBE_HABER, MONTOMOV, NUMDEPTO, TIPCAMBIO, CONTRAPAR, ORDEN, CCOSTOS, CGRUPOS, IDINFADIPAR, IDUUID) 
                        VALUES ('$subTipo', '$row->NUM_POLIZ', $par, $periodo, $ejercicio, '$ctaProv', '$fecha', '$conceptoA1', 'D', $montoP, 0, 1, 0, $par, 0,0, null , null)";
            $this->grabaBD();
            /// Banco 1002-001-001 Haber
            $par++;
            $this->query="INSERT INTO AUXILIAR$eje (TIPO_POLI, NUM_POLIZ, NUM_PART, PERIODO, EJERCICIO, NUM_CTA, FECHA_POL, CONCEP_PO, DEBE_HABER, MONTOMOV, NUMDEPTO, TIPCAMBIO, CONTRAPAR, ORDEN, CCOSTOS, CGRUPOS, IDINFADIPAR, IDUUID) 
                        VALUES ('$subTipo', '$row->NUM_POLIZ', $par, $periodo, $ejercicio, '$ctaBanco', '$fecha', '$conceptoA2', 'H', $montoB, 0, 1, 0, $par, 0,0, null , null)";
            $this->grabaBD();
            /// IVA Acreditable pagado 1180-001-000 Debe
            $ctaIVAap ='118000100000000000002';
            $par++;
            $this->query="INSERT INTO AUXILIAR$eje (TIPO_POLI, NUM_POLIZ, NUM_PART, PERIODO, EJERCICIO, NUM_CTA, FECHA_POL, CONCEP_PO, DEBE_HABER, MONTOMOV, NUMDEPTO, TIPCAMBIO, CONTRAPAR, ORDEN, CCOSTOS, CGRUPOS, IDINFADIPAR, IDUUID) 
                        VALUES ('$subTipo', '$row->NUM_POLIZ', $par, $periodo, $ejercicio, '$ctaIVAap', '$fecha', '$conceptoIA', 'D', $montoI, 0, 1, 0, $par, 0,0, null , null)";
            $this->grabaBD();
            //// IVA pendiente de pago  1190-001-000 haber 
            $ctaIVApp ='119000100000000000002';
            $par++;
            $this->query="INSERT INTO AUXILIAR$eje (TIPO_POLI, NUM_POLIZ, NUM_PART, PERIODO, EJERCICIO, NUM_CTA, FECHA_POL, CONCEP_PO, DEBE_HABER, MONTOMOV, NUMDEPTO, TIPCAMBIO, CONTRAPAR, ORDEN, CCOSTOS, CGRUPOS, IDINFADIPAR, IDUUID) 
                        VALUES ('$subTipo', '$row->NUM_POLIZ', $par, $periodo, $ejercicio, '$ctaIVApp', '$fecha', '$conceptoIP', 'H', $montoI, 0, 1, 0, $par, 0,0, null , null)";
            $this->grabaBD();
            return array("status"=>'ok', "mensaje"=>'Se genero la poliza'.$row->NUM_POLIZ);            
        }
    }
}      
?>
