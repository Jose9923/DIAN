<?php

namespace App\Http\Controllers\Nomina;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class XmlNominaController extends Controller
{
    public function crearXML(Request $request){

        $xmlSinFirmar = '';

        $request = json_decode(json_encode($request->all()));
        // log::info("Request: " . json_encode($request));
        $tipoAmbiente = $request->tipoAmbiente; // 2 = Pruebba | 1 = Produccion
        $doctype = strtolower($request->doctype); // Convertimos a minúsculas para evitar problemas de mayúsculas
        if ($doctype == 'nie') {
            $tipoXml = 102; // Nómina Individual
        } else {
            $tipoXml = 103; // Nota de Ajuste
        }

        if ($tipoAmbiente == 2) {
            $Url = "https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey="; // Ambiente Habilitación (Pruebas)
        } elseif ($tipoAmbiente == 1) {
            $Url = "https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey="; // Ambiente Producción
        }

        // Datos Dian

        // Datos Provedor Tecnologico (Sisoft)
        $SoftwarePin = '123456';
        $nitSisoft = '900364032';
        $dvSisoft = '2';
        $idSoftware = 'ce029721-1611-45e9-9722-ecaa1075bf1b';

        //totales
        $devengadosTotal = $request->devengadosTotal;
        $deduccionesTotal = $request->deduccionesTotal;
        $comprobanteTotal = $request->comprobanteTotal;

        //datos Empleado
        $nitEmpleado = $request->nitEmpleado;
        // Datos Emision
        $fechaEmision = date('Y-m-d');
        $horaEmision = date('H:i:s') . '-05:00';
        $sequence = $request->sequence;
        $prefijo = $request->prefijo;
        $nominaId = "{$prefijo}{$sequence}";
        $huella = $this->generarHuellaSoftware($idSoftware, $SoftwarePin, $nominaId);
        $CUNE = $this->generarCUNE($nominaId, $fechaEmision, $horaEmision, $devengadosTotal, $deduccionesTotal, $comprobanteTotal, $nitSisoft, $nitEmpleado, $tipoXml, $SoftwarePin,  $tipoAmbiente);

        // formPeriodo
        $fechaIngreso = $request->fechaIngreso;
        $fechaRetiro = $request->fechaRetiro;
        $fechaLiquidacionInicio = $request->fechaLiquidacionInicio;
        $fechaLiquidacionFin = $request->fechaLiquidacionFin;
        $tiempoLaborado = $request->tiempoLaborado;
        $fechaGen = $fechaEmision;

        // formNumeroSecuenciaXML
        $codigoTrabajador = $request->codigoTrabajador;
        $consecutivo = $sequence;
        $numero = $nominaId;

        // formLugarGeneracionXML
        $pais = $request->pais;
        $departamentoEstado = $request->departamentoEstado;
        $municipioCiudad = $request->municipioCiudad;
        $idioma = $request->idioma;

        // formProveedorXML
        $razonSocial = $request->razonSocial;
        $primerApellido = $request->primerApellido;
        $segundoApellido = $request->segundoApellido;
        $primerNombre = $request->primerNombre;
        $otrosNombres = $request->otrosNombres;
        $nit = $request->nit;
        $dv = $this->calcularDV($nit);
        $softwareID = $idSoftware;
        $softwareSC = $huella;

        //empleador
        $paisEmpleador = $request->paisEmpleador;
        $departamentoEstadoEmpleador = $request->departamentoEstadoEmpleador;
        $municipioCiudadEmpleador = $request->municipioCiudadEmpleador;
        $direccionEmpleador= $request->direccionEmpleador;

        //qr
        $qrCode = $this->generarQR($nominaId, $fechaEmision, $horaEmision, $nitSisoft, $nitEmpleado, $devengadosTotal, $deduccionesTotal, $comprobanteTotal, $CUNE, $Url);//preguntar si ese nit empleado es de sisoft o unitropico

        // formInformacionGeneral
        $version = "V1.0: Documento Soporte de Pago de Nómina Electrónica";
        $ambiente = $tipoAmbiente;
        $encripCUNE = "CUNE-SHA384";
        $periodoNomina = $request->periodoNomina;// tabla 5.5.1 peridos de pago 
        $tipoMoneda = $request->tipoMoneda;
        $trm = $request->trm;

        //formNotas
        $notas = $request->notas ?? [];// N etiquetas notas

        //formTrabajador
        $tipoTrabajador = $request->tipoTrabajador;
        $subTipoTrabajador = $request->subTipoTrabajador;
        $altoRiesgoPension = $request->altoRiesgoPension;
        $tipoDocumento = $request->tipoDocumento;
        $numeroDocumento = $request->numeroDocumento;
        $razonSocial = $request->razonSocial;
        $primerApellidoT = $request->primerApellidoT;
        $segundoApellidoT = $request->segundoApellidoT;
        $primerNombreT = $request->primerNombreT;
        $otrosNombresT = $request->otrosNombresT;
        $lugarTrabajoPais = $request->lugarTrabajoPais;
        $lugarTrabajoDepartamentoEstado = $request->lugarTrabajoDepartamentoEstado;
        $lugarTrabajoMunicipioCiudad = $request->lugarTrabajoMunicipioCiudad;
        $lugarTrabajoDireccion = $request->lugarTrabajoDireccion;
        $salarioIntegral = $request->salarioIntegral;
        $tipoContrato = $request->tipoContrato;// del 1 al 5 tabla 5.5.2
        $sueldo = $request->sueldo;

        //formPago
        $forma = $request->forma;
        $metodo = $request->metodo;
        $banco = $request->banco;
        $tipoCuenta = $request->tipoCuenta;
        $numeroCuenta = $request->numeroCuenta;

        //formFechaPago
        $fechaPago = $request->fechaPago ?? [];//N etiquetas fecha pagos

        //formDevengados
        //Basico
        $diasTrabajados = $request->diasTrabajados;
        $sueldoTrabajado = $request->sueldoTrabajado;

        $transporte = $request->transporte ?? [];
        $heds = $request->heds ?? [];
        $hens = $request->hens ?? [];
        $hrns = $request->hrns ?? [];
        $heddfs = $request->heddfs ?? [];
        $hrddfs = $request->hrddfs ?? [];
        $hendfs = $request->hendfs ?? [];
        $hrndfs = $request->hrndfs ?? [];
        $vacacionesComunes = $request->vacacionesComunes ?? [];
        $vacacionesCompensadas = $request->vacacionesCompensadas ?? [];

        //primas
        $cantidadP = $request->cantidadP;
        $pagoP = $request->pagoP;
        $pagoNS = $request->pagoNS;

        //cesantias
        $pagoC = $request->pagoC;
        $porcentajeC = $request->porcentajeC;
        $pagoIntereses = $request->pagoIntereses;
        //incapacidades
        $incapacidades = $request->incapacidades ?? [];

        //licencias
        $licenciasMP = $request->licenciasMP ?? [];
        $licenciasR = $request->licenciasR ?? [];
        $licenciasNR = $request->licenciasNR ?? [];

        //Bonificaciones
        $bonificaciones = $request->bonificaciones ?? [];

        //auxilios
        $auxilios = $request->auxilios ?? [];

        //Huelga Legal
        $huelgasLegales = $request->huelgasLegales ?? [];

        //Otros Conceptos
        $otrosConceptos = $request->otrosConceptos ?? [];

        //Compensaciones
        $compensaciones = $request->compensaciones ?? [];

        //bonoEPCTVsXML
        $bonoEPCTVs = $request->bonoEPCTVs ?? [];

        //Comisiones
        $comisiones  = $request->comisiones  ?? [];

        //pagosTerceros
        $pagosTerceros  = $request->pagosTerceros  ?? [];

        //anticipos
        $anticipos  = $request->anticipos  ?? [];

        //deducciones
        $saludPorcentaje =$request->saludPorcentaje;
        $saludDeduccion =$request->saludDeduccion;
        $fondoPensionPorcentaje = $request->fondoPensionPorcentaje;
        $fondoPensionDeduccion =$request->fondoPensionDeduccion;
        $fondoSPPorcentaje =$request->fondoSPPorcentaje;
        $fondoSPDeduccion =$request->fondoSPDeduccion;
        $porcentajeSub =$request->porcentajeSub;
        $deduccionSub =$request->deduccionSub;
        $sindicatos = $request->sindicatos  ?? [];
        $sanciones = $request->sanciones  ?? [];
        $libranzas = $request->libranzas  ?? [];
        $pagosTercerosDeduccion = $request->pagosTercerosDeduccion  ?? [];
        $anticiposDeduccion = $request->anticiposDeduccion  ?? [];
        $otrasDeducciones = $request->otrasDeducciones  ?? [];
        $pensionVoluntaria = $request->pensionVoluntaria;
        $retencionFuente = $request->retencionFuente;
        $afc = $request->afc;
        $cooperativa = $request->cooperativa;
        $embargoFiscal = $request->embargoFiscal;
        $planComplementarios = $request->planComplementarios;
        $educacion = $request->educacion;
        $reintegro = $request->reintegro;
        $deuda = $request->deuda;

        $redondeo = $request->redondeo;
        
        $basico = $this->formBasico($diasTrabajados, $sueldoTrabajado); // Obligatorio
        $transporteXML = $this->formTransporte($transporte);
        $hedsXML = $this->formHEDs($heds);
        $hensXML = $this->formHENs($hens);
        $hrnsXML = $this->formHRNs($hrns);
        $heddfsXML = $this->formHEDDFs($heddfs);
        $hrddfsXML = $this->formHRDDFs($hrddfs);
        $hendfsXML = $this->formHENDFs($hendfs);
        $hrndfsXML = $this->formHRNDFs($hrndfs);
        $vacacionesXML = $this->formVacaciones($vacacionesComunes, $vacacionesCompensadas);
        $primas = $this->formPrimas($cantidadP, $pagoP, $pagoNS);
        $cesantias = $this->formCesantias($pagoC, $porcentajeC, $pagoIntereses);
        $incapacidadesXML = $this->formIncapacidades($incapacidades);
        $licencias = $this->formLicencias($licenciasMP, $licenciasR, $licenciasNR);
        $bonificacionesXML = $this->formBonificaciones($bonificaciones);
        $auxiliosXML = $this->formAuxilios($auxilios);
        $huelgasLegalesXML = $this->formHuelgasLegales($huelgasLegales);
        $otrosConceptosXML = $this->formOtrosConceptos($otrosConceptos);
        $compensacionesXML = $this->formCompensaciones($compensaciones);
        $bonoEPCTVsXML = $this->formBonoEPCTVs($bonoEPCTVs);
        $comisionesXML = $this->formComisiones($comisiones);
        $pagosTercerosXML = $this->formPagosTerceros($pagosTerceros);
        $anticiposXML = $this->formAnticipos($anticipos);
        $dotacion = $request->dotacion ?? '';
        $apoyoSost = $request->apoyoSost ?? '';
        $teletrabajo = $request->teletrabajo ?? '';
        $bonifRetiro = $request->bonifRetiro ?? '';
        $indemnizacion = $request->indemnizacion ?? '';
        $reintegro = $request->reintegro ?? '';

        // LLAMADO DE FUNCIONES PARA CONSTRUIR EL .XML
        $headXML = $this->formHeadXML();
        $firmaXML = $this->formSignatureXML();
        $periodoXML = $this->formPeriodo($fechaIngreso, $fechaRetiro, $fechaLiquidacionInicio, $fechaLiquidacionFin, $tiempoLaborado, $fechaGen);
        $numeroSecuenciaXML = $this->formNumeroSecuenciaXML($codigoTrabajador, $prefijo, $consecutivo, $numero);
        $lugarGeneracionXML = $this->formLugarGeneracionXML($pais, $departamentoEstado, $municipioCiudad, $idioma);
        $proveedorXML = $this->formProveedorXML($razonSocial, $primerApellido, $segundoApellido, $primerNombre, $otrosNombres, $nitSisoft, $dvSisoft, $softwareID, $softwareSC);
        $codigoQRXML = $this->formCodigoQR($qrCode);
        $informacionGeneralXML = $this->formInformacionGeneral($version, $ambiente, $tipoXml, $CUNE, $encripCUNE, $fechaEmision, $horaEmision, $periodoNomina, $tipoMoneda, $trm);
        $notasXML = $this->formNotas($notas);
        $empleadorXML = $this->formEmpleador($razonSocial, $primerApellido, $segundoApellido, $primerNombre, $otrosNombres, $nit, $dv, $paisEmpleador, $departamentoEstadoEmpleador, $municipioCiudadEmpleador, $direccionEmpleador);
        $trabajadorXML = $this->formTrabajador($tipoTrabajador, $subTipoTrabajador, $altoRiesgoPension, $tipoDocumento, $numeroDocumento, $primerApellidoT, $segundoApellidoT, $primerNombreT, $otrosNombresT, $lugarTrabajoPais, $lugarTrabajoDepartamentoEstado, $lugarTrabajoMunicipioCiudad, $lugarTrabajoDireccion, $salarioIntegral, $tipoContrato, $sueldo, $codigoTrabajador);
        $pagoXML = $this->formPago($forma, $metodo, $banco, $tipoCuenta, $numeroCuenta);
        $fechaPagoXML = $this->formFechaPago($fechaPago);
        $devengadosXML = $this->formDevengados($basico, $transporteXML, $hedsXML, $hensXML, $hrnsXML, $heddfsXML, $hrddfsXML, $hendfsXML, $hrndfsXML, $vacacionesXML, $primas, $cesantias, $incapacidadesXML, $licencias, $bonificacionesXML, $auxiliosXML, $huelgasLegalesXML, $otrosConceptosXML, $compensacionesXML, $bonoEPCTVsXML, $comisionesXML, $pagosTercerosXML, $anticiposXML, $dotacion, $apoyoSost, $teletrabajo, $bonifRetiro, $indemnizacion, $reintegro);
        $deduccionesXML = $this->formDeducciones($saludPorcentaje, $saludDeduccion, $fondoPensionPorcentaje, $fondoPensionDeduccion, $fondoSPPorcentaje, $fondoSPDeduccion, $porcentajeSub, $deduccionSub, $sindicatos, $sanciones, $libranzas, $pagosTercerosDeduccion, $anticiposDeduccion, $otrasDeducciones,$pensionVoluntaria,$retencionFuente,$afc,$cooperativa, $embargoFiscal, $planComplementarios, $educacion, $reintegro, $deuda );
        $redondeoXML = $this->formRedondeo($redondeo);

        $devengadosTotalXML = $this->formDevengadosTotal($devengadosTotal);
        $deduccionesTotalXML = $this->formDeduccionesTotal($deduccionesTotal);
        $comprobanteTotalXML = $this->formComprobanteTotal($comprobanteTotal);
        $xmlSinFirmar = $headXML .$firmaXML. $periodoXML . $numeroSecuenciaXML . $lugarGeneracionXML . $proveedorXML . $codigoQRXML . $informacionGeneralXML . $notasXML . $empleadorXML . $trabajadorXML . $pagoXML . $fechaPagoXML . $devengadosXML . $deduccionesXML . $redondeoXML . $devengadosTotalXML . $deduccionesTotalXML . $comprobanteTotalXML . '</NominaIndividual>';

        return response()->json([
            'xmlSinFirmar' => $xmlSinFirmar,
            'fechaEmision' => $fechaEmision,
            'horaEmision' => $horaEmision,
            'factura_id' => $nominaId,
            'sequence' => $sequence,
            'cune' => $CUNE,
            'qrlink' => $Url . $CUNE,
        ]);
    }

    public function formHeadXML() {
        return '<NominaIndividual xmlns="dian:gov:co:facturaelectronica:NominaIndividual" xmlns:xs="http://www.w3.org/2001/XMLSchema-instance" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2" xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" xmlns:xades141="http://uri.etsi.org/01903/v1.4.1#" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" SchemaLocation="" xsi:schemaLocation="dian:gov:co:facturaelectronica:NominaIndividual NominaIndividualElectronicaXSD.xsd"> ';
    }
    public function formSignatureXML(){
        $string = "
        <ext:UBLExtensions>
                <ext:UBLExtension>
                    <ext:ExtensionContent></ext:ExtensionContent>
                </ext:UBLExtension>
            </ext:UBLExtensions>";

        return $string;
    }
    public function formPeriodo($fechaIngreso, $fechaRetiro, $fechaLiquidacionInicio, $fechaLiquidacionFin, $tiempoLaborado, $fechaGen) {
        // Atributos obligatorios
        $atributos = [
            'FechaIngreso' => $fechaIngreso,
            'FechaLiquidacionInicio' => $fechaLiquidacionInicio,
            'FechaLiquidacionFin' => $fechaLiquidacionFin,
            'TiempoLaborado' => $tiempoLaborado,
            'FechaGen' => $fechaGen,
        ];
    
        // Agregar FechaRetiro solo si no está vacía
        if (!empty($fechaRetiro)) {
            $atributos['FechaRetiro'] = $fechaRetiro;
        }
    
        // Construir la cadena de atributos
        $atributosString = '';
        foreach ($atributos as $nombre => $valor) {
            $atributosString .= " $nombre='$valor'";
        }
    
        // Retornar la etiqueta <Periodo> con los atributos
        return "<Periodo$atributosString/>";
    }
    
    public function formNumeroSecuenciaXML($codigoTrabajador, $prefijo, $consecutivo, $numero) {
        // Atributos obligatorios
        $atributos = [
            'Consecutivo' => $consecutivo,
            'Numero' => $numero,
        ];
    
        // Agregar CodigoTrabajador solo si no está vacío
        if (!empty($codigoTrabajador)) {
            $atributos['CodigoTrabajador'] = $codigoTrabajador;
        }
    
        // Agregar Prefijo solo si no está vacío
        if (!empty($prefijo)) {
            $atributos['Prefijo'] = $prefijo;
        }
    
        // Construir la cadena de atributos
        $atributosString = '';
        foreach ($atributos as $nombre => $valor) {
            $atributosString .= " $nombre='$valor'";
        }
    
        // Retornar la etiqueta <NumeroSecuenciaXML> con los atributos
        return "<NumeroSecuenciaXML$atributosString/>";
    }
    
    public function formLugarGeneracionXML($pais, $departamentoEstado, $municipioCiudad, $idioma) {
        return "<LugarGeneracionXML Pais='$pais' DepartamentoEstado='$departamentoEstado' MunicipioCiudad='$municipioCiudad' Idioma='$idioma'/>";
    }
    
    public function formProveedorXML($razonSocial, $primerApellido, $segundoApellido, $primerNombre, $otrosNombres, $nit, $dv, $softwareID, $softwareSC) {
        // Atributos obligatorios
        $atributos = [
            'NIT' => $nit,
            'DV' => $dv,
            'SoftwareID' => $softwareID,
            'SoftwareSC' => $softwareSC,
        ];
    
        // Agregar atributos no obligatorios solo si no están vacíos
        if (!empty($razonSocial)) {
            $atributos['RazonSocial'] = $razonSocial;
        }
        if (!empty($primerApellido)) {
            $atributos['PrimerApellido'] = $primerApellido;
        }
        if (!empty($segundoApellido)) {
            $atributos['SegundoApellido'] = $segundoApellido;
        }
        if (!empty($primerNombre)) {
            $atributos['PrimerNombre'] = $primerNombre;
        }
        if (!empty($otrosNombres)) {
            $atributos['OtrosNombres'] = $otrosNombres;
        }
    
        // Construir la cadena de atributos
        $atributosString = '';
        foreach ($atributos as $nombre => $valor) {
            $atributosString .= " $nombre='$valor'";
        }
    
        // Retornar la etiqueta <ProveedorXML> con los atributos
        return "<ProveedorXML$atributosString/>";
    }
    public function formCodigoQR($qrCode) {
        return "<CodigoQR>$qrCode</CodigoQR>";
    }
    public function formInformacionGeneral($version, $ambiente, $tipoXML, $cune, $encripCUNE, $fechaGen, $horaGen, $periodoNomina, $tipoMoneda, $trm) {
        // Atributos obligatorios
        $atributos = [
            'Version' => $version,
            'Ambiente' => $ambiente,
            'TipoXML' => $tipoXML,
            'CUNE' => $cune,
            'EncripCUNE' => $encripCUNE,
            'FechaGen' => $fechaGen,
            'HoraGen' => $horaGen,
            'PeriodoNomina' => $periodoNomina,
            'TipoMoneda' => $tipoMoneda,
        ];
    
        // Agregar TRM solo si no está vacío
        if (!empty($trm)) {
            $atributos['TRM'] = $trm;
        }
    
        // Construir la cadena de atributos
        $atributosString = '';
        foreach ($atributos as $nombre => $valor) {
            $atributosString .= " $nombre='$valor'";
        }
    
        // Retornar la etiqueta <InformacionGeneral> con los atributos
        return "<InformacionGeneral$atributosString/>";
    }
    public function formNotas($notas) {

        $notasXML = '';
        // Verificar si $notas está vacío o no está definido
        if (empty($notas)) {
            return $notasXML; // No generar ninguna etiqueta
        }

        // Si $notas es un array
        if (is_array($notas)) {
            foreach ($notas as $nota) {
                if (!empty($nota)) { // Solo agregar notas no vacías
                    $notasXML .= "<Notas>$nota</Notas>";
                }
            }
        }
        // Si $notas es un objeto
        elseif (is_object($notas)) {
            foreach ($notas as $nota) {
                if (!empty($nota)) { // Solo agregar notas no vacías
                    $notasXML .= "<Notas>$nota</Notas>";
                }
            }
        }
        // Si $notas es un string (una sola nota)
        else {
            $notasXML = "<Notas>$notas</Notas>";
        }

        return $notasXML;
    }
    public function formEmpleador($razonSocial, $primerApellido, $segundoApellido, $primerNombre, $otrosNombres, $nit, $dv, $pais, $departamentoEstado, $municipioCiudad, $direccion) {
        // Atributos obligatorios
        $atributos = [
            'NIT' => $nit,
            'DV' => $dv,
            'Pais' => $pais,
            'DepartamentoEstado' => $departamentoEstado,
            'MunicipioCiudad' => $municipioCiudad,
            'Direccion' => $direccion,
        ];
    
        // Agregar atributos no obligatorios solo si no están vacíos
        if (!empty($razonSocial)) {
            $atributos['RazonSocial'] = $razonSocial;
        }
        if (!empty($primerApellido)) {
            $atributos['PrimerApellido'] = $primerApellido;
        }
        if (!empty($segundoApellido)) {
            $atributos['SegundoApellido'] = $segundoApellido;
        }
        if (!empty($primerNombre)) {
            $atributos['PrimerNombre'] = $primerNombre;
        }
        if (!empty($otrosNombres)) {
            $atributos['OtrosNombres'] = $otrosNombres;
        }
    
        // Construir la cadena de atributos
        $atributosString = '';
        foreach ($atributos as $nombre => $valor) {
            $atributosString .= " $nombre='$valor'";
        }
    
        // Retornar la etiqueta <Empleador> con los atributos
        return "<Empleador$atributosString/>";
    }

    public function formTrabajador($tipoTrabajador, $subTipoTrabajador, $altoRiesgoPension, $tipoDocumento, $numeroDocumento, $primerApellido, $segundoApellido, $primerNombre, $otrosNombres, $lugarTrabajoPais, $lugarTrabajoDepartamentoEstado, $lugarTrabajoMunicipioCiudad, $lugarTrabajoDireccion, $salarioIntegral, $tipoContrato, $sueldo, $codigoTrabajador) {
        // Atributos obligatorios
        $atributos = [
            'TipoTrabajador' => $tipoTrabajador,
            'SubTipoTrabajador' => $subTipoTrabajador,
            'AltoRiesgoPension' => $altoRiesgoPension,
            'TipoDocumento' => $tipoDocumento,
            'NumeroDocumento' => $numeroDocumento,
            'PrimerApellido' => $primerApellido,
            'SegundoApellido' => $segundoApellido,
            'PrimerNombre' => $primerNombre,
            'LugarTrabajoPais' => $lugarTrabajoPais,
            'LugarTrabajoDepartamentoEstado' => $lugarTrabajoDepartamentoEstado,
            'LugarTrabajoMunicipioCiudad' => $lugarTrabajoMunicipioCiudad,
            'LugarTrabajoDireccion' => $lugarTrabajoDireccion,
            'SalarioIntegral' => $salarioIntegral,
            'TipoContrato' => $tipoContrato,
            'Sueldo' => $sueldo,
        ];
    
        // Agregar atributos no obligatorios solo si no están vacíos
        if (!empty($otrosNombres)) {
            $atributos['OtrosNombres'] = $otrosNombres;
        }
        if (!empty($codigoTrabajador)) {
            $atributos['CodigoTrabajador'] = $codigoTrabajador;
        }
    
        // Construir la cadena de atributos
        $atributosString = '';
        foreach ($atributos as $nombre => $valor) {
            $atributosString .= " $nombre='$valor'";
        }
    
        // Retornar la etiqueta <Trabajador> con los atributos
        return "<Trabajador$atributosString/>";
    }

    public function formPago($forma, $metodo, $banco, $tipoCuenta, $numeroCuenta) {
        // Atributos obligatorios
        $atributos = [
            'Forma' => $forma,
            'Metodo' => $metodo,
        ];
    
        // Agregar atributos no obligatorios solo si no están vacíos
        if (!empty($banco)) {
            $atributos['Banco'] = $banco;
        }
        if (!empty($tipoCuenta)) {
            $atributos['TipoCuenta'] = $tipoCuenta;
        }
        if (!empty($numeroCuenta)) {
            $atributos['NumeroCuenta'] = $numeroCuenta;
        }
    
        // Construir la cadena de atributos
        $atributosString = '';
        foreach ($atributos as $nombre => $valor) {
            $atributosString .= " $nombre='$valor'";
        }
    
        // Retornar la etiqueta <Pago> con los atributos
        return "<Pago$atributosString/>";
    }
    public function formFechaPago($fechaPago) {
        $fechasPagoXML = '';
    
        // Si $fechaPago es un array
        if (is_array($fechaPago)) {
            foreach ($fechaPago as $fecha) {
                if (!empty($fecha)) { // Solo agregar fechas no vacías
                    $fechasPagoXML .= "<FechaPago>$fecha</FechaPago>";
                }
            }
        }
        // Si $fechaPago es un string (una sola fecha)
        else {
            if (!empty($fechaPago)) { // Solo agregar si no está vacía
                $fechasPagoXML = "<FechaPago>$fechaPago</FechaPago>";
            }
        }
    
        // Si hay fechas, envolverlas en <FechasPagos>
        if (!empty($fechasPagoXML)) {
            return "<FechasPagos>$fechasPagoXML</FechasPagos>";
        }
    
        // Si no hay fechas, retornar una cadena vacía
        return '';
    }



       public function formDevengados($basico, $transporteXML, $hedsXML, $hensXML, $hrnsXML, $heddfsXML, $hrddfsXML, $hendfsXML, $hrndfsXML, $vacacionesXML, $primas, $cesantias, $incapacidadesXML, $licencias, $bonificacionesXML, $auxiliosXML, $huelgasLegalesXML, $otrosConceptosXML, $compensacionesXML, $bonoEPCTVsXML, $comisionesXML, $pagosTercerosXML, $anticiposXML, $dotacion, $apoyoSost, $teletrabajo, $bonifRetiro, $indemnizacion, $reintegro) {
                // formDevengados

        $devengadosXML = '';

        $devengadosXML .= $basico;

        if (!empty($transporteXML)) {
            $devengadosXML .= $transporteXML;
        }
        if (!empty($hedsXML)) {
            $devengadosXML .= $hedsXML;
        }
        if (!empty($hensXML)) {
            $devengadosXML .= $hensXML;
        }
        if (!empty($hrnsXML)) {
            $devengadosXML .= $hrnsXML;
        }
        if (!empty($heddfsXML)) {
            $devengadosXML .= $heddfsXML;
        }
        if (!empty($hrddfsXML)) {
            $devengadosXML .= $hrddfsXML;
        }
        if (!empty($hendfsXML)) {
            $devengadosXML .= $hendfsXML;
        }
        if (!empty($hrndfsXML)) {
            $devengadosXML .= $hrndfsXML;
        }
        if (!empty($vacacionesXML)) {
            $devengadosXML .= $vacacionesXML;
        }
        if (!empty($primas)) {
            $devengadosXML .= $primas;
        }
        if (!empty($cesantias)) {
            $devengadosXML .= $cesantias;
        }
        if (!empty($incapacidadesXML)) {
            $devengadosXML .= $incapacidadesXML;
        }
        if (!empty($licencias)) {
            $devengadosXML .= $licencias;
        }
        if (!empty($bonificacionesXML)) {
            $devengadosXML .= $bonificacionesXML;
        }
        if (!empty($auxiliosXML)) {
            $devengadosXML .= $auxiliosXML;
        }
        if (!empty($huelgasLegalesXML)) {
            $devengadosXML .= $huelgasLegalesXML;
        }
        if (!empty($otrosConceptosXML)) {
            $devengadosXML .= $otrosConceptosXML;
        }
        if (!empty($compensacionesXML)) {
            $devengadosXML .= $compensacionesXML;
        }
        if (!empty($bonoEPCTVsXML)) {
            $devengadosXML .= $bonoEPCTVsXML;
        }
        if (!empty($comisionesXML)) {
            $devengadosXML .= $comisionesXML;
        }
        if (!empty($pagosTercerosXML)) {
            $devengadosXML .= $pagosTercerosXML;
        }
        if (!empty($anticiposXML)) {
            $devengadosXML .= $anticiposXML;
        }
        if (!empty($dotacion)) {
            $devengadosXML .= "<Dotacion>$dotacion</Dotacion>";
        }
        if (!empty($apoyoSost)) {
            $devengadosXML .= "<ApoyoSost>$apoyoSost</ApoyoSost>";
        }
        if (!empty($teletrabajo)) {
            $devengadosXML .= "<Teletrabajo>$teletrabajo</Teletrabajo>";
        }
        if (!empty($bonifRetiro)) {
            $devengadosXML .= "<BonifRetiro>$bonifRetiro</BonifRetiro>";
        }
        if (!empty($indemnizacion)) {
            $devengadosXML .= "<Indemnizacion>$indemnizacion</Indemnizacion>";
        }
        if (!empty($reintegro)) {
            $devengadosXML .= "<Reintegro>$reintegro</Reintegro>";
        }
    
        // Si hay elementos, envolverlos en <Devengados>
        if (!empty($devengadosXML)) {
            return "<Devengados>$devengadosXML</Devengados>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }

    public function formDeducciones($saludPorcentaje, $saludDeduccion, $fondoPensionPorcentaje, $fondoPensionDeduccion, $fondoSPPorcentaje, $fondoSPDeduccion, $porcentajeSub, $deduccionSub, $sindicatos, $sanciones, $libranzas, $pagosTercerosDeduccion, $anticiposDeduccion, $otrasDeducciones,$pensionVoluntaria,$retencionFuente,$afc,$cooperativa, $embargoFiscal, $planComplementarios, $educacion, $reintegro, $deuda ) {
        $sindicatos = json_decode(json_encode($sindicatos), true);
        $sanciones = json_decode(json_encode($sanciones), true);
        $libranzas = json_decode(json_encode($libranzas), true);
        $pagosTercerosDeduccion = json_decode(json_encode($pagosTercerosDeduccion), true);
        $anticiposDeduccion = json_decode(json_encode($anticiposDeduccion), true);
        $otrasDeducciones = json_decode(json_encode($otrasDeducciones), true);
        $xml = "<Deducciones>";
        $xml .= "    <Salud Porcentaje='$saludPorcentaje' Deduccion='$saludDeduccion'/>";
        $xml .= "    <FondoPension Porcentaje='$fondoPensionPorcentaje' Deduccion='$fondoPensionDeduccion'/>";
        $xml .= $this->formFondoSP($fondoSPPorcentaje, $fondoSPDeduccion, $porcentajeSub, $deduccionSub);
        
        if (!empty($sindicatos)) { // Verificamos si el array de sindicatos no está vacío
            $xml .= "    <Sindicatos>";
            foreach ($sindicatos as $sindicato) {
                // Validamos que los campos obligatorios estén presentes
                if (isset($sindicato['Porcentaje']) && isset($sindicato['Deduccion'])) {
                    $xml .= "        <Sindicato Porcentaje='{$sindicato['Porcentaje']}' Deduccion='{$sindicato['Deduccion']}'/>";
                }
            }
            $xml .= "    </Sindicatos>";
        }
        if (!empty($sanciones)) { // Verificamos si el array de sanciones no está vacío
            $xml .= "    <Sanciones>";
            foreach ($sanciones as $sancion) {
                // Validamos que los campos obligatorios estén presentes
                if (isset($sancion['SancionPublic']) && isset($sancion['SancionPriv'])) {
                    $xml .= "        <Sancion SancionPublic='{$sancion['SancionPublic']}' SancionPriv='{$sancion['SancionPriv']}'/>";
                }
            }
            $xml .= "    </Sanciones>";
        }
        if (!empty($libranzas)) { // Verificamos si el array de libranzas no está vacío
            $xml .= "    <Libranzas>";
            foreach ($libranzas as $libranza) {
                // Validamos que los campos obligatorios estén presentes
                if (isset($libranza['Descripcion']) && isset($libranza['Deduccion'])) {
                    $xml .= "        <Libranza Descripcion='{$libranza['Descripcion']}' Deduccion='{$libranza['Deduccion']}'/>";
                }
            }
            $xml .= "    </Libranzas>";
        }
        
        if (!empty($pagosTercerosDeduccion)) { // Verificamos si el array de pagos a terceros no está vacío
            $xml .= "    <PagosTerceros>";
            foreach ($pagosTercerosDeduccion as $pagoTerceroDeduccion) {
                // Agregamos cada PagoTercero al XML
                $xml .= "        <PagoTercero>$pagoTerceroDeduccion</PagoTercero>";
            }
            $xml .= "    </PagosTerceros>";
        }

        if (!empty($anticiposDeduccion)) { // Verificamos si el array de anticipos no está vacío
            $xml .= "    <Anticipos>";
            foreach ($anticiposDeduccion as $anticipoDeduccion) {
                // Agregamos cada Anticipo al XML
                $xml .= "        <Anticipo>$anticipoDeduccion</Anticipo>";
            }
            $xml .= "    </Anticipos>";
        }

        if (!empty($otrasDeducciones)) { // Verificamos si el array de otraDeduccion no está vacío
            $xml .= "    <OtrasDeducciones>";
            foreach ($otrasDeducciones as $otraDeduccion) {
                // Agregamos cada otraDeduccion al XML
                $xml .= "        <OtraDeduccion>$otraDeduccion</OtraDeduccion>";
            }
            $xml .= "    </OtrasDeducciones>";
        }
        
        foreach (['PensionVoluntaria' => $pensionVoluntaria, 'RetencionFuente' => $retencionFuente, 'AFC' => $afc, 'Cooperativa' => $cooperativa, 'EmbargoFiscal' => $embargoFiscal, 'PlanComplementarios' => $planComplementarios, 'Educacion' => $educacion, 'Reintegro' => $reintegro, 'Deuda' => $deuda] as $tag => $value) {
            if ($value) {
                $xml .= "    <$tag>$value</$tag>";
            }
        }
        
        $xml .= "</Deducciones>";
        
        return $xml;
    }
    
    public function formRedondeo($redondeo) {
        if (empty($redondeo)){
            return '';
        }
        return "<Redondeo>$redondeo</Redondeo>";
    }
    public function formDevengadosTotal($devengadosTotal) {
        return "<DevengadosTotal>$devengadosTotal</DevengadosTotal>";
    }
    public function formDeduccionesTotal($deduccionesTotal) {
        return "<DeduccionesTotal>$deduccionesTotal</DeduccionesTotal>";
    }
    public function formComprobanteTotal($comprobanteTotal) {
        return "<ComprobanteTotal>$comprobanteTotal</ComprobanteTotal>";
    }
    //Devengos

    public function formBasico($diasTrabajados, $sueldoTrabajado) {
        // Generar la etiqueta <Basico> con sus atributos
        return "<Basico DiasTrabajados='$diasTrabajados' SueldoTrabajado='$sueldoTrabajado'/>";
    }

    public function formTransporte($transporte) {
        $transporteXML = '';
    
        // Iterar sobre cada instancia de transporte
        foreach ($transporte as $instancia) {
            // Acceder a las propiedades del objeto usando la sintaxis de objetos (->)
            $auxilioTransporte = $instancia->auxilioTransporte ?? '';
            $viaticoManuAlojS = $instancia->viaticoManuAlojS ?? '';
            $viaticoManuAlojNS = $instancia->viaticoManuAlojNS ?? '';
    
            // Generar la etiqueta <Transporte> para la instancia actual
            $atributos = [];
            if (!empty($auxilioTransporte)) {
                $atributos[] = "AuxilioTransporte='$auxilioTransporte'";
            }
            if (!empty($viaticoManuAlojS)) {
                $atributos[] = "ViaticoManuAlojS='$viaticoManuAlojS'";
            }
            if (!empty($viaticoManuAlojNS)) {
                $atributos[] = "ViaticoManuAlojNS='$viaticoManuAlojNS'";
            }
    
            // Si hay atributos, generar la etiqueta <Transporte>
            if (!empty($atributos)) {
                $transporteXML .= "<Transporte " . implode(' ', $atributos) . "/>";
            }
        }
    
        return $transporteXML;
    }
    public function formHEDs($heds) {
        // Convertir objetos a arrays
        $heds = json_decode(json_encode($heds), true);
    
        $hedsXML = '';
    
        foreach ($heds as $hed) {
            $hedXML = "<HED";
    
            // Agregar horaInicio solo si no está vacía
            if (!empty($hed['horaInicio'])) {
                $hedXML .= " HoraInicio='{$hed['horaInicio']}'";
            }
    
            // Agregar horaFin solo si no está vacía
            if (!empty($hed['horaFin'])) {
                $hedXML .= " HoraFin='{$hed['horaFin']}'";
            }
    
            // Agregar los campos obligatorios
            $hedXML .= " Cantidad='{$hed['cantidad']}'";
            $hedXML .= " Porcentaje='{$hed['porcentaje']}'";
            $hedXML .= " Pago='{$hed['pago']}'";
    
            $hedXML .= "></HED>";
            $hedsXML .= $hedXML;
        }
    
        // Si hay elementos, envolverlos en <HEDs>
        if (!empty($hedsXML)) {
            return "<HEDs>{$hedsXML}</HEDs>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }
    
    public function formHENs($hens) {
        $hens = json_decode(json_encode($hens), true);
        // Si $hens es null o no es un array, retornar una cadena vacía
        if ($hens === null || !is_array($hens)) {
            return '';
        }
    
        $hensXML = '';
    
        // Recorrer cada HEN y agregarla a la etiqueta <HENs>
        foreach ($hens as $hen) {
            $henXML = "<HEN";
    
            // Agregar horaInicio solo si no está vacía
            if (!empty($hen['horaInicio'])) {
                $henXML .= " HoraInicio='{$hen['horaInicio']}'";
            }
    
            // Agregar horaFin solo si no está vacía
            if (!empty($hen['horaFin'])) {
                $henXML .= " HoraFin='{$hen['horaFin']}'";
            }
    
            // Agregar los campos obligatorios
            $henXML .= " Cantidad='{$hen['cantidad']}'";
            $henXML .= " Porcentaje='{$hen['porcentaje']}'";
            $henXML .= " Pago='{$hen['pago']}'";
    
            $henXML .= "></HEN>";
            $hensXML .= $henXML;
        }
    
        // Si hay elementos, envolverlos en <HENs>
        if (!empty($hensXML)) {
            return "<HENs>{$hensXML}</HENs>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }
    public function formHRNs($hrns) {
        $hrns = json_decode(json_encode($hrns), true);
        // Si $hrns es null o no es un array, retornar una cadena vacía
        if ($hrns === null || !is_array($hrns)) {
            return '';
        }
    
        $hrnsXML = '';
    
        // Recorrer cada HRN y agregarla a la etiqueta <HRNs>
        foreach ($hrns as $hrn) {
            $hrnXML = "<HRN";
    
            // Agregar horaInicio solo si no está vacía
            if (!empty($hrn['horaInicio'])) {
                $hrnXML .= " HoraInicio='{$hrn['horaInicio']}'";
            }
    
            // Agregar horaFin solo si no está vacía
            if (!empty($hrn['horaFin'])) {
                $hrnXML .= " HoraFin='{$hrn['horaFin']}'";
            }
    
            // Agregar los campos obligatorios
            $hrnXML .= " Cantidad='{$hrn['cantidad']}'";
            $hrnXML .= " Porcentaje='{$hrn['porcentaje']}'";
            $hrnXML .= " Pago='{$hrn['pago']}'";
    
            $hrnXML .= "></HRN>";
            $hrnsXML .= $hrnXML;
        }
    
        // Si hay elementos, envolverlos en <HRNs>
        if (!empty($hrnsXML)) {
            return "<HRNs>{$hrnsXML}</HRNs>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }
    public function formHEDDFs($heddfs) {
        $heddfs = json_decode(json_encode($heddfs), true);
        // Si $heddfs es null o no es un array, retornar una cadena vacía
        if ($heddfs === null || !is_array($heddfs)) {
            return '';
        }
    
        $heddfsXML = '';
    
        // Recorrer cada HEDDF y agregarla a la etiqueta <HEDDFs>
        foreach ($heddfs as $heddf) {
            $heddfXML = "<HEDDF";
    
            // Agregar horaInicio solo si no está vacía
            if (!empty($heddf['horaInicio'])) {
                $heddfXML .= " HoraInicio='{$heddf['horaInicio']}'";
            }
    
            // Agregar horaFin solo si no está vacía
            if (!empty($heddf['horaFin'])) {
                $heddfXML .= " HoraFin='{$heddf['horaFin']}'";
            }
    
            // Agregar los campos obligatorios
            $heddfXML .= " Cantidad='{$heddf['cantidad']}'";
            $heddfXML .= " Porcentaje='{$heddf['porcentaje']}'";
            $heddfXML .= " Pago='{$heddf['pago']}'";
    
            $heddfXML .= "></HEDDF>";
            $heddfsXML .= $heddfXML;
        }
    
        // Si hay elementos, envolverlos en <HEDDFs>
        if (!empty($heddfsXML)) {
            return "<HEDDFs>{$heddfsXML}</HEDDFs>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }
    public function formHRDDFs($hrddfs) {
        $hrddfs = json_decode(json_encode($hrddfs), true);
        // Si $hrddfs es null o no es un array, retornar una cadena vacía
        if ($hrddfs === null || !is_array($hrddfs)) {
            return '';
        }
    
        $hrddfsXML = '';
    
        // Recorrer cada HRDDF y agregarla a la etiqueta <HRDDFs>
        foreach ($hrddfs as $hrddf) {
            $hrddfXML = "<HRDDF";
    
            // Agregar horaInicio solo si no está vacía
            if (!empty($hrddf['horaInicio'])) {
                $hrddfXML .= " HoraInicio='{$hrddf['horaInicio']}'";
            }
    
            // Agregar horaFin solo si no está vacía
            if (!empty($hrddf['horaFin'])) {
                $hrddfXML .= " HoraFin='{$hrddf['horaFin']}'";
            }
    
            // Agregar los campos obligatorios
            $hrddfXML .= " Cantidad='{$hrddf['cantidad']}'";
            $hrddfXML .= " Porcentaje='{$hrddf['porcentaje']}'";
            $hrddfXML .= " Pago='{$hrddf['pago']}'";
    
            $hrddfXML .= "></HRDDF>";
            $hrddfsXML .= $hrddfXML;
        }
    
        // Si hay elementos, envolverlos en <HRDDFs>
        if (!empty($hrddfsXML)) {
            return "<HRDDFs>{$hrddfsXML}</HRDDFs>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }
    public function formHENDFs($hendfs) {
        $hendfs = json_decode(json_encode($hendfs), true);
        // Si $hendfs es null o no es un array, retornar una cadena vacía
        if ($hendfs === null || !is_array($hendfs)) {
            return '';
        }
    
        $hendfsXML = '';
    
        // Recorrer cada HENDF y agregarla a la etiqueta <HENDFs>
        foreach ($hendfs as $hendf) {
            $hendfXML = "<HENDF";
    
            // Agregar horaInicio solo si no está vacía
            if (!empty($hendf['horaInicio'])) {
                $hendfXML .= " HoraInicio='{$hendf['horaInicio']}'";
            }
    
            // Agregar horaFin solo si no está vacía
            if (!empty($hendf['horaFin'])) {
                $hendfXML .= " HoraFin='{$hendf['horaFin']}'";
            }
    
            // Agregar los campos obligatorios
            $hendfXML .= " Cantidad='{$hendf['cantidad']}'";
            $hendfXML .= " Porcentaje='{$hendf['porcentaje']}'";
            $hendfXML .= " Pago='{$hendf['pago']}'";
    
            $hendfXML .= "></HENDF>";
            $hendfsXML .= $hendfXML;
        }
    
        // Si hay elementos, envolverlos en <HENDFs>
        if (!empty($hendfsXML)) {
            return "<HENDFs>{$hendfsXML}</HENDFs>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }
    public function formHRNDFs($hrndfs) {
        $hrndfs = json_decode(json_encode($hrndfs), true);
        // Si $hrndfs es null o no es un array, retornar una cadena vacía
        if ($hrndfs === null || !is_array($hrndfs)) {
            return '';
        }
    
        $hrndfsXML = '';
    
        // Recorrer cada HRNDF y agregarla a la etiqueta <HRNDFs>
        foreach ($hrndfs as $hrndf) {
            $hrndfXML = "<HRNDF";
    
            // Agregar horaInicio solo si no está vacía
            if (!empty($hrndf['horaInicio'])) {
                $hrndfXML .= " HoraInicio='{$hrndf['horaInicio']}'";
            }
    
            // Agregar horaFin solo si no está vacía
            if (!empty($hrndf['horaFin'])) {
                $hrndfXML .= " HoraFin='{$hrndf['horaFin']}'";
            }
    
            // Agregar los campos obligatorios
            $hrndfXML .= " Cantidad='{$hrndf['cantidad']}'";
            $hrndfXML .= " Porcentaje='{$hrndf['porcentaje']}'";
            $hrndfXML .= " Pago='{$hrndf['pago']}'";
    
            $hrndfXML .= "></HRNDF>";
            $hrndfsXML .= $hrndfXML;
        }
    
        // Si hay elementos, envolverlos en <HRNDFs>
        if (!empty($hrndfsXML)) {
            return "<HRNDFs>{$hrndfsXML}</HRNDFs>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }
    public function formVacaciones($vacacionesComunes, $vacacionesCompensadas) {
        $vacacionesComunes = json_decode(json_encode($vacacionesComunes), true);
        $vacacionesCompensadas = json_decode(json_encode($vacacionesCompensadas), true);
        // Si no hay datos, retornar una cadena vacía
        if (empty($vacacionesComunes) && empty($vacacionesCompensadas)) {
            return '';
        }
    
        $vacacionesXML = '';
    
        // Procesar VacacionesComunes
        if (!empty($vacacionesComunes)) {
            foreach ($vacacionesComunes as $comun) {
                // Verificar que los campos obligatorios estén presentes
                if (isset($comun['cantidad']) && isset($comun['pago'])) {
                    $comunXML = "<VacacionesComunes";
                    if (!empty($comun['fechaInicio'])) {
                        $comunXML .= " FechaInicio='{$comun['fechaInicio']}'";
                    }
                    if (!empty($comun['fechaFin'])) {
                        $comunXML .= " FechaFin='{$comun['fechaFin']}'";
                    }
                    $comunXML .= " Cantidad='{$comun['cantidad']}'";
                    $comunXML .= " Pago='{$comun['pago']}'";
                    $comunXML .= "></VacacionesComunes>";
                    $vacacionesXML .= $comunXML;
                }
            }
        }
    
        // Procesar VacacionesCompensadas
        if (!empty($vacacionesCompensadas)) {
            foreach ($vacacionesCompensadas as $compensada) {
                // Verificar que los campos obligatorios estén presentes
                if (isset($compensada['cantidad']) && isset($compensada['pago'])) {
                    $compensadaXML = "<VacacionesCompensadas";
                    $compensadaXML .= " Cantidad='{$compensada['cantidad']}'";
                    $compensadaXML .= " Pago='{$compensada['pago']}'";
                    $compensadaXML .= "></VacacionesCompensadas>";
                    $vacacionesXML .= $compensadaXML;
                }
            }
        }
    
        // Si hay elementos, envolverlos en <Vacaciones>
        if (!empty($vacacionesXML)) {
            return "<Vacaciones>{$vacacionesXML}</Vacaciones>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }
    public function formPrimas($cantidad, $pago, $pagoNS = null) {
        // Verificar que los campos obligatorios estén presentes
        if (empty($cantidad) || empty($pago)) {
            return '';
        }
    
        $primasXML = "<Primas";
        $primasXML .= " Cantidad='{$cantidad}'";
        $primasXML .= " Pago='{$pago}'";
    
        // Agregar PagoNS solo si no está vacío
        if (!empty($pagoNS)) {
            $primasXML .= " Pago='{$pagoNS}'";
        }
    
        $primasXML .= "></Primas>";
    
        return $primasXML;
    }
    public function formCesantias($pagoC, $porcentajeC, $pagoIntereses) {

        // Verificar que los campos obligatorios estén presentes
        if (($pagoC === null || $pagoC === '') || ($porcentajeC === null || $porcentajeC === '') || ($pagoIntereses === null || $pagoIntereses === '') ) {
            return '';
        }

        $cesantiasXML = "<Cesantias";
        $cesantiasXML .= " Pago='{$pagoC}'";
        $cesantiasXML .= " Porcentaje='{$porcentajeC}'";
        $cesantiasXML .= " PagoIntereses='{$pagoIntereses}'";
        $cesantiasXML .= "></Cesantias>";
    
        return $cesantiasXML;
    }

    public function formIncapacidades($incapacidades) {
        $incapacidades = json_decode(json_encode($incapacidades), true);
        // Si no hay datos, retornar una cadena vacía
        if (empty($incapacidades)) {
            return '';
        }
    
        $incapacidadesXML = '';
    
        // Procesar cada Incapacidad
        foreach ($incapacidades as $incapacidad) {
            // Verificar que los campos obligatorios estén presentes
            if (isset($incapacidad['cantidad']) && isset($incapacidad['tipo']) && isset($incapacidad['pago'])) {
                $incapacidadXML = "<Incapacidad";
    
                // Agregar FechaInicio solo si no está vacía
                if (!empty($incapacidad['fechaInicio'])) {
                    $incapacidadXML .= " FechaInicio='{$incapacidad['fechaInicio']}'";
                }
    
                // Agregar FechaFin solo si no está vacía
                if (!empty($incapacidad['fechaFin'])) {
                    $incapacidadXML .= " FechaFin='{$incapacidad['fechaFin']}'";
                }
    
                // Agregar campos obligatorios
                $incapacidadXML .= " Cantidad='{$incapacidad['cantidad']}'";
                $incapacidadXML .= " Tipo='{$incapacidad['tipo']}'";//tabla 5.5.6
                $incapacidadXML .= " Pago='{$incapacidad['pago']}'";
    
                $incapacidadXML .= "></Incapacidad>";
                $incapacidadesXML .= $incapacidadXML;
            }
        }
    
        // Si hay elementos, envolverlos en <Incapacidades>
        if (!empty($incapacidadesXML)) {
            return "<Incapacidades>{$incapacidadesXML}</Incapacidades>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }
    public function formLicencias($licenciasMP = [], $licenciasR = [], $licenciasNR = []) {
        $licenciasMP = json_decode(json_encode($licenciasMP), true);
        $licenciasR = json_decode(json_encode($licenciasR), true);
        $licenciasNR = json_decode(json_encode($licenciasNR), true);
        // Si no hay datos, retornar una cadena vacía
        if (empty($licenciasMP) && empty($licenciasR) && empty($licenciasNR)) {
            return '';
        }
    
        $licenciasXML = '';
    
        // Procesar LicenciaMP
        if (!empty($licenciasMP)) {
            foreach ($licenciasMP as $licenciaMP) {
                // Verificar que los campos obligatorios estén presentes
                if (isset($licenciaMP['cantidad']) && isset($licenciaMP['pago'])) {
                    $licenciaMPXML = "<LicenciaMP";
    
                    // Agregar FechaInicio solo si no está vacía
                    if (!empty($licenciaMP['fechaInicio'])) {
                        $licenciaMPXML .= " FechaInicio='{$licenciaMP['fechaInicio']}'";
                    }
    
                    // Agregar FechaFin solo si no está vacía
                    if (!empty($licenciaMP['fechaFin'])) {
                        $licenciaMPXML .= " FechaFin='{$licenciaMP['fechaFin']}'";
                    }
    
                    // Agregar campos obligatorios
                    $licenciaMPXML .= " Cantidad='{$licenciaMP['cantidad']}'";
                    $licenciaMPXML .= " Pago='{$licenciaMP['pago']}'";
    
                    $licenciaMPXML .= "></LicenciaMP>";
                    $licenciasXML .= $licenciaMPXML;
                }
            }
        }
    
        // Procesar LicenciaR
        if (!empty($licenciasR)) {
            foreach ($licenciasR as $licenciaR) {
                // Verificar que los campos obligatorios estén presentes
                if (isset($licenciaR['cantidad']) && isset($licenciaR['pago'])) {
                    $licenciaRXML = "<LicenciaR";
    
                    // Agregar FechaInicio solo si no está vacía
                    if (!empty($licenciaR['fechaInicio'])) {
                        $licenciaRXML .= " FechaInicio='{$licenciaR['fechaInicio']}'";
                    }
    
                    // Agregar FechaFin solo si no está vacía
                    if (!empty($licenciaR['fechaFin'])) {
                        $licenciaRXML .= " FechaFin='{$licenciaR['fechaFin']}'";
                    }
    
                    // Agregar campos obligatorios
                    $licenciaRXML .= " Cantidad='{$licenciaR['cantidad']}'";
                    $licenciaRXML .= " Pago='{$licenciaR['pago']}'";
    
                    $licenciaRXML .= "></LicenciaR>";
                    $licenciasXML .= $licenciaRXML;
                }
            }
        }
    
        // Procesar LicenciaNR
        if (!empty($licenciasNR)) {
            foreach ($licenciasNR as $licenciaNR) {
                // Verificar que el campo obligatorio esté presente
                if (isset($licenciaNR['cantidad'])) {
                    $licenciaNRXML = "<LicenciaNR";
    
                    // Agregar FechaInicio solo si no está vacía
                    if (!empty($licenciaNR['fechaInicio'])) {
                        $licenciaNRXML .= " FechaInicio='{$licenciaNR['fechaInicio']}'";
                    }
    
                    // Agregar FechaFin solo si no está vacía
                    if (!empty($licenciaNR['fechaFin'])) {
                        $licenciaNRXML .= " FechaFin='{$licenciaNR['fechaFin']}'";
                    }
    
                    // Agregar campo obligatorio
                    $licenciaNRXML .= " Cantidad='{$licenciaNR['cantidad']}'";
    
                    $licenciaNRXML .= "></LicenciaNR>";
                    $licenciasXML .= $licenciaNRXML;
                }
            }
        }
    
        // Si hay elementos, envolverlos en <Licencias>
        if (!empty($licenciasXML)) {
            return "<Licencias>{$licenciasXML}</Licencias>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }
    public function formBonificaciones($bonificaciones) {
        $bonificaciones = json_decode(json_encode($bonificaciones), true);
        // Si no hay datos, retornar una cadena vacía
        if (empty($bonificaciones)) {
            return '';
        }
    
        $bonificacionesXML = '';
    
        // Procesar cada Bonificacion
        foreach ($bonificaciones as $bonificacion) {
            $bonificacionXML = "<Bonificacion";
    
            // Agregar BonificacionS si está presente
            if (!empty($bonificacion['BonificacionS'])) {
                $bonificacionXML .= " BonificacionS='{$bonificacion['BonificacionS']}'";
            }
    
            // Agregar BonificacionNS si está presente
            if (!empty($bonificacion['BonificacionNS'])) {
                $bonificacionXML .= " BonificacionNS='{$bonificacion['BonificacionNS']}'";
            }
    
            $bonificacionXML .= "></Bonificacion>";
            $bonificacionesXML .= $bonificacionXML;
        }
    
        // Si hay elementos, envolverlos en <Bonificaciones>
        if (!empty($bonificacionesXML)) {
            return "<Bonificaciones>{$bonificacionesXML}</Bonificaciones>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }
    public function formAuxilios($auxilios) {
        $auxilios = json_decode(json_encode($auxilios), true);
        // Si no hay datos, retornar una cadena vacía
        if (empty($auxilios)) {
            return '';
        }
    
        $auxiliosXML = '';
    
        // Procesar cada Auxilio
        foreach ($auxilios as $auxilio) {
            $auxilioXML = "<Auxilio";
    
            // Agregar AuxilioS si está presente
            if (!empty($auxilio['AuxilioS'])) {
                $auxilioXML .= " AuxilioS='{$auxilio['AuxilioS']}'";
            }
    
            // Agregar AuxilioNS si está presente
            if (!empty($auxilio['AuxilioNS'])) {
                $auxilioXML .= " AuxilioNS='{$auxilio['AuxilioNS']}'";
            }
    
            $auxilioXML .= "></Auxilio>";
            $auxiliosXML .= $auxilioXML;
        }
    
        // Si hay elementos, envolverlos en <Auxilios>
        if (!empty($auxiliosXML)) {
            return "<Auxilios>{$auxiliosXML}</Auxilios>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }
    public function formHuelgasLegales($huelgasLegales) {
        $huelgasLegales = json_decode(json_encode($huelgasLegales), true);
        // Si no hay datos, retornar una cadena vacía
        if (empty($huelgasLegales)) {
            return '';
        }
    
        $huelgasLegalesXML = '';
    
        // Procesar cada HuelgaLegal
        foreach ($huelgasLegales as $huelgaLegal) {
            // Verificar que el campo obligatorio esté presente
            if (isset($huelgaLegal['Cantidad'])) {
                $huelgaLegalXML = "<HuelgaLegal";
    
                // Agregar FechaInicio solo si no está vacía
                if (!empty($huelgaLegal['FechaInicio'])) {
                    $huelgaLegalXML .= " FechaInicio='{$huelgaLegal['FechaInicio']}'";
                }
    
                // Agregar FechaFin solo si no está vacía
                if (!empty($huelgaLegal['FechaFin'])) {
                    $huelgaLegalXML .= " FechaFin='{$huelgaLegal['FechaFin']}'";
                }
    
                // Agregar campo obligatorio
                $huelgaLegalXML .= " Cantidad='{$huelgaLegal['Cantidad']}'";
    
                $huelgaLegalXML .= "></HuelgaLegal>";
                $huelgasLegalesXML .= $huelgaLegalXML;
            }
        }
    
        // Si hay elementos, envolverlos en <HuelgasLegales>
        if (!empty($huelgasLegalesXML)) {
            return "<HuelgasLegales>{$huelgasLegalesXML}</HuelgasLegales>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }

    public function formOtrosConceptos($otrosConceptos) {
        $otrosConceptos = json_decode(json_encode($otrosConceptos), true);
        // Si no hay datos, retornar una cadena vacía
        if (empty($otrosConceptos)) {
            return '';
        }
    
        $otrosConceptosXML = '';
    
        // Procesar cada OtroConcepto
        foreach ($otrosConceptos as $otroConcepto) {
            // Verificar que el campo obligatorio esté presente
            if (isset($otroConcepto['DescripcionConcepto'])) {
                $otroConceptoXML = "<OtroConcepto";
    
                // Agregar campo obligatorio
                $otroConceptoXML .= " DescripcionConcepto='{$otroConcepto['DescripcionConcepto']}'";
    
                // Agregar ConceptoS solo si no está vacío
                if (!empty($otroConcepto['ConceptoS'])) {
                    $otroConceptoXML .= " ConceptoS='{$otroConcepto['ConceptoS']}'";
                }
    
                // Agregar ConceptoNS solo si no está vacío
                if (!empty($otroConcepto['ConceptoNS'])) {
                    $otroConceptoXML .= " ConceptoNS='{$otroConcepto['ConceptoNS']}'";
                }
    
                $otroConceptoXML .= "></OtroConcepto>";
                $otrosConceptosXML .= $otroConceptoXML;
            }
        }
    
        // Si hay elementos, envolverlos en <OtrosConceptos>
        if (!empty($otrosConceptosXML)) {
            return "<OtrosConceptos>{$otrosConceptosXML}</OtrosConceptos>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }

    public function formCompensaciones($compensaciones) {
        $compensaciones = json_decode(json_encode($compensaciones), true);
        // Si no hay datos, retornar una cadena vacía
        if (empty($compensaciones)) {
            return '';
        }
    
        $compensacionesXML = '';
    
        // Procesar cada Compensacion
        foreach ($compensaciones as $compensacion) {
            // Verificar que los campos obligatorios estén presentes
            if (isset($compensacion['CompensacionO']) && isset($compensacion['CompensacionE'])) {
                $compensacionXML = "<Compensacion";
    
                // Agregar campos obligatorios
                $compensacionXML .= " CompensacionO='{$compensacion['CompensacionO']}'";
                $compensacionXML .= " CompensacionE='{$compensacion['CompensacionE']}'";
    
                $compensacionXML .= "></Compensacion>";
                $compensacionesXML .= $compensacionXML;
            }
        }
    
        // Si hay elementos, envolverlos en <Compensaciones>
        if (!empty($compensacionesXML)) {
            return "<Compensaciones>{$compensacionesXML}</Compensaciones>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }
    public function formBonoEPCTVs($bonoEPCTVs) {
        $bonoEPCTVs = json_decode(json_encode($bonoEPCTVs), true);
        // Si no hay datos, retornar una cadena vacía
        if (empty($bonoEPCTVs)) {
            return '';
        }
    
        $bonoEPCTVsXML = '';
    
        // Procesar cada BonoEPCTV
        foreach ($bonoEPCTVs as $bonoEPCTV) {
            $bonoEPCTVXML = "<BonoEPCTV";
    
            // Agregar PagoS solo si no está vacío
            if (!empty($bonoEPCTV['PagoS'])) {
                $bonoEPCTVXML .= " PagoS='{$bonoEPCTV['PagoS']}'";
            }
    
            // Agregar PagoNS solo si no está vacío
            if (!empty($bonoEPCTV['PagoNS'])) {
                $bonoEPCTVXML .= " PagoNS='{$bonoEPCTV['PagoNS']}'";
            }
    
            // Agregar PagoAlimentacionS solo si no está vacío
            if (!empty($bonoEPCTV['PagoAlimentacionS'])) {
                $bonoEPCTVXML .= " PagoAlimentacionS='{$bonoEPCTV['PagoAlimentacionS']}'";
            }
    
            // Agregar PagoAlimentacionNS solo si no está vacío
            if (!empty($bonoEPCTV['PagoAlimentacionNS'])) {
                $bonoEPCTVXML .= " PagoAlimentacionNS='{$bonoEPCTV['PagoAlimentacionNS']}'";
            }
    
            $bonoEPCTVXML .= "></BonoEPCTV>";
            $bonoEPCTVsXML .= $bonoEPCTVXML;
        }
    
        // Si hay elementos, envolverlos en <BonoEPCTVs>
        if (!empty($bonoEPCTVsXML)) {
            return "<BonoEPCTVs>{$bonoEPCTVsXML}</BonoEPCTVs>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }
    public function formComisiones($comisiones) {
        
        // Si no hay datos, retornar una cadena vacía
        if (empty($comisiones)) {
            return '';
        }
        
        $comisionesXML = '';
        
        // Procesar cada Comision
        foreach ($comisiones as $comision) {
            if (!empty($comision)) {
                $comisionesXML .= "<Comision>{$comision}</Comision>";
            }
        }
        
        // Si hay elementos, envolverlos en <Comisiones>
        if (!empty($comisionesXML)) {
            return "<Comisiones>{$comisionesXML}</Comisiones>";
        }
        
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }

    public function formPagosTerceros($pagosTerceros) {
        // Si no hay datos, retornar una cadena vacía
        if (empty($pagosTerceros)) {
            return '';
        }
    
        $pagosTercerosXML = '';
    
        // Procesar cada PagoTercero
        foreach ($pagosTerceros as $pagoTercero) {
            // Verificar que el valor de PagoTercero no esté vacío
            if (!empty($pagoTercero)) {
                $pagosTercerosXML .= "<PagoTercero>{$pagoTercero}</PagoTercero>";
            }
        }
    
        // Si hay elementos, envolverlos en <PagosTerceros>
        if (!empty($pagosTercerosXML)) {
            return "<PagosTerceros>{$pagosTercerosXML}</PagosTerceros>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }
    public function formAnticipos($anticipos) {
        // Si no hay datos, retornar una cadena vacía
        if (empty($anticipos)) {
            return '';
        }
    
        $anticiposXML = '';
    
        // Procesar cada Anticipo
        foreach ($anticipos as $anticipo) {
            // Verificar que el valor de Anticipo no esté vacío
            if (!empty($anticipo)) {
                $anticiposXML .= "<Anticipo>{$anticipo}</Anticipo>";
            }
        }
    
        // Si hay elementos, envolverlos en <Anticipos>
        if (!empty($anticiposXML)) {
            return "<Anticipos>{$anticiposXML}</Anticipos>";
        }
    
        // Si no hay elementos, retornar una cadena vacía
        return '';
    }

    //deducciones
    public function formFondoSP($fondoSPPorcentaje = null, $fondoSPDeduccion = null, $porcentajeSub = null, $deduccionSub = null) {
        // Si todos los atributos están vacíos, retornar una cadena vacía
        if (empty($fondoSPPorcentaje) && empty($fondoSPDeduccion) && empty($porcentajeSub) && empty($deduccionSub)) {
            return '';
        }
    
        $fondoSPXML = "<FondoSP";
    
        // Agregar atributos solo si no están vacíos
        if (!empty($fondoSPPorcentaje)) {
            $fondoSPXML .= " Porcentaje='{$fondoSPPorcentaje}'";
        }
        if (!empty($fondoSPDeduccion)) {
            $fondoSPXML .= " DeduccionSP='{$fondoSPDeduccion}'";
        }
        if (!empty($porcentajeSub)) {
            $fondoSPXML .= " PorcentajeSub='{$porcentajeSub}'";
        }
        if (!empty($deduccionSub)) {
            $fondoSPXML .= " DeduccionSub='{$deduccionSub}'";
        }
    
        $fondoSPXML .= "/>";
    
        return $fondoSPXML;
    }
    public function generarCUNE($nominaId, $fechaEmision, $horaEmision, $devengadosTotal, $deduccionesTotal, $comprobanteTotal, $nitSisoft, $nitEmpleado, $tipoXml, $SoftwarePin,  $tipoAmbiente){

        $NumNE = $nominaId;
        $FecNE = $fechaEmision;
        $HorNE = $horaEmision;
        $ValDev = $devengadosTotal.".00";
        $ValDed = $deduccionesTotal.".00";
        $ValTolNE = $comprobanteTotal.".00";//devengo-deducciones
        $NitNE = $nitSisoft;
        $DocEmp = $nitEmpleado;
        $TipoXML = $tipoXml;
        $Software_Pin = $SoftwarePin;
        $TipAmb = $tipoAmbiente;

        $CUNE = "{$NumNE}{$FecNE}{$HorNE}{$ValDev}{$ValDed}{$ValTolNE}{$NitNE}{$DocEmp}{$TipoXML}{$Software_Pin}{$TipAmb}";
        $UUID = hash('sha384', $CUNE);
        return $UUID;
    }

    public function generarHuellaSoftware($SoftwareId,$SoftwarePin,$nominaId ){

        $SoftwareSecurityCode = hash('sha384', $SoftwareId . $SoftwarePin . $nominaId);

        return $SoftwareSecurityCode;
    }

    public function generarQR($nominaId, $fechaEmision, $horaEmision, $nitSisoft, $nitEmpleado, $devengadosTotal, $deduccionesTotal, $comprobanteTotal, $CUNE, $Url){
        $qrcode = "NumNIE=$nominaId FecNIE=$fechaEmision HorNIE=$horaEmision NitNIE=$nitSisoft DocEmp=$nitEmpleado ValDev=$devengadosTotal ValDed=$deduccionesTotal ValTol=$comprobanteTotal CUNE=$CUNE URL=$Url$CUNE";
        return $qrcode;
    }

    public function formatoMonedaDian($valor){
        $valor = round($valor, 2, PHP_ROUND_HALF_EVEN);
        $valor = number_format($valor, 2, '.', '');

        return $valor;
    }

    function calcularDV($nit) {
        $ponderadores = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];

        // Convertir el NIT a una cadena de caracteres
        $nit = strval($nit);
        $longitud = strlen($nit);
        $suma = 0;

        // Invertir el NIT para aplicar los ponderadores desde el último dígito
        for ($i = 0; $i < $longitud; $i++) {
            $suma += $nit[$longitud - $i - 1] * $ponderadores[$i];
        }

        $residuo = $suma % 11;

        if ($residuo == 0 || $residuo == 1) {
            return $residuo;
        } else {
            return 11 - $residuo;
        }
    }
}
