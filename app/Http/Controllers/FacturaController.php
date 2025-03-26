<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\XmlController;
use App\Http\Controllers\SoapController;
use App\Http\Controllers\FirmaController;
use Illuminate\Support\Facades\Log;

class FacturaController extends Controller
{
    public function crearFacturaElectronica(Request $request){
        $doctype = $request->doctype;
        $xmlController = new XmlController();
        $xmlResponse = $xmlController->crearXML($request);
        $xmlSinFirmar = $xmlResponse['xmlSinFirmar'];
        $qrlink = $xmlResponse['qrlink'];

        $firmaController = new FirmaController();
        $xmlFirmado = $firmaController->firmarXML($xmlSinFirmar, $doctype, $xmlResponse['fechaEmision'], $xmlResponse['horaEmision'], $xmlResponse['sequence']);

        //return $xmlSinFirmar;

        $soapController = new SoapController();
        $response = $soapController->enviarPeticion($xmlFirmado['nombre_zip'], $xmlFirmado['zip_base64'], $request->tipoAmbiente);
        $repuestaDian = $this->procesarRespuestaFactura($response);
        return response()->json([
            'qrlink' => $qrlink,
            'cufe' => $xmlResponse['cufe'],
            'factura_id' => $xmlResponse['factura_id'],
            'fecha_emision' => $xmlResponse['fechaEmision'],
            'nombre_zip' => $xmlFirmado['nombre_zip'],
            'zip_base64' => $xmlFirmado['zip_base64'],
            'response' => $response,
            'respuesta_dian' => $repuestaDian,
        ], 200);
    }

    public function generarCUFE(Request $request){
        $inicioFactura = $request->factura;
        $from = '990000000';
        $prefix = 'SETP';
        $preFactura = $from + $inicioFactura;
        $facturaId = $prefix . $preFactura;

        $fechaEmision = $request->fecha_emision;
        $horaEmision = $request->hora_emision . '-05:00';

        $valorTotal = '1000.00';
        $companyNit = '900364032';
        $cedulaCliente = '1122655531';
        $tipoAmbiente = '2';
        $claveTecnica2 = 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c';

        $NumFac = $facturaId;
        $FecFac = $fechaEmision;
        $HorFac = $horaEmision;
        $ValFac = $valorTotal;
        $Codimp1 = '01';
        $ValImp1 = '0.00';
        $CodImp2 = '04';
        $ValImp2 = '0.00';
        $CodImp3 = '03';
        $ValImp3 = '0.00';
        $ValTot = $valorTotal;
        $NitFE = $companyNit;
        $NumAdq = $cedulaCliente;
        $claveTecnica = $claveTecnica2;
        $tipoAmbiente1 = $tipoAmbiente;

        $CUFE = $NumFac . $FecFac . $HorFac . $ValFac . $Codimp1 . $ValImp1 . $CodImp2 . $ValImp2 . $CodImp3 . $ValImp3 . $ValTot . $NitFE . $NumAdq . $claveTecnica . $tipoAmbiente1;

        $UUID = hash('sha384', $CUFE);
        $SoftwareId = '51cb05b8-2031-468f-b2d8-fdcafd34e44b';
        $SoftwarePin = '12345';

        $SoftwareSecurityCode = hash('sha384', $SoftwareId . $SoftwarePin . $facturaId);

        $formattedNit = str_pad($companyNit, 10, '0', STR_PAD_LEFT);
        $sequence = $preFactura;
        $yearXml = date('Y'); // Año calendario

        $filename = $this->nombre_xml($formattedNit, $yearXml, $sequence);

        $zipFileName = $this->nombre_zip($formattedNit, $yearXml, $sequence);

        return response()->json([
            'huella_software' => $SoftwareSecurityCode,
            'CUFE' => $UUID,
            'nombre_xml' => $filename,
            'nombre_zip' => $zipFileName,
        ], 200);
    }

    function nombre_xml($nitCompany, $yearXml, $sequence){
        $formattedNit = str_pad($nitCompany, 10, '0', STR_PAD_LEFT);
        $codigoPT = '000';
        $yearSuffix = substr($yearXml, -2);
        $formattedSequence = str_pad(dechex($sequence), 8, '0', STR_PAD_LEFT);

        // Nombre del archivo
        $xmlFileName = 'fv' . $formattedNit . $codigoPT . $yearSuffix . $formattedSequence .'.xml';

        return $xmlFileName;
    }

    function nombre_zip($nitCompany, $yearXml, $sequence){
        $formattedNit = str_pad($nitCompany, 10, '0', STR_PAD_LEFT);
        $codigoPT = '000';
        $yearSuffix = substr($yearXml, -2);
        $formattedSequence = str_pad(dechex($sequence), 8, '0', STR_PAD_LEFT);

        // Nombre del archivo
        $xmlFileName = 'z' . $formattedNit . $codigoPT . $yearSuffix . $formattedSequence .'.zip';

        return $xmlFileName;
    }

    public function procesarRespuestaFactura($response)
    {
        try {
            // Convertir la cadena XML en un objeto SimpleXMLElement
            $xml = new \SimpleXMLElement($response);

            // Obtener los namespaces del XML
            $namespaces = $xml->getNamespaces(true);

            // Acceder al cuerpo del mensaje
            $body = $xml->children($namespaces['s'])->Body;
            $sendBillSyncResponse = $body->children($namespaces[''])->SendBillSyncResponse;
            $sendBillSyncResult = $sendBillSyncResponse->children($namespaces[''])->SendBillSyncResult;

            // Obtener los mensajes de error
            $errorMessages = $sendBillSyncResult->children($namespaces['b'])->ErrorMessage;

            $errors = [];
            if ($errorMessages) {
                foreach ($errorMessages->children($namespaces['c']) as $error) {
                    $errors[] = (string) $error;
                }
            }

            // Extraer otros datos importantes
            $isValid = (string) $sendBillSyncResult->children($namespaces['b'])->IsValid;
            $statusCode = (string) $sendBillSyncResult->children($namespaces['b'])->StatusCode;
            $statusDescription = (string) $sendBillSyncResult->children($namespaces['b'])->StatusDescription;
            $statusMessage = (string) $sendBillSyncResult->children($namespaces['b'])->StatusMessage;
            $xmlDocumentKey = (string) $sendBillSyncResult->children($namespaces['b'])->XmlDocumentKey;

            // Loguear la información
            Log::info("Factura procesada:", [
                'IsValid' => $isValid,
                'StatusCode' => $statusCode,
                'StatusDescription' => $statusDescription,
                'StatusMessage' => $statusMessage,
                'XmlDocumentKey' => $xmlDocumentKey,
                'Errors' => $errors,
            ]);

            return response()->json([
                'success' => true,
                'is_valid' => $isValid,
                'status_code' => $statusCode,
                'status_description' => $statusDescription,
                'status_message' => $statusMessage,
                'xml_document_key' => $xmlDocumentKey,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            Log::error("Error procesando la respuesta XML: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al procesar la factura'], 500);
        }
    }

    public function firmarXmlPrueba(Request $request)
    {
        $firmaController = new FirmaController();
        $xmlSinFirmar ='<?xml version="1.0" encoding="UTF-8"?>
<NominaIndividual xmlns="dian:gov:co:facturaelectronica:NominaIndividual"
                  xmlns:xs="http://www.w3.org/2001/XMLSchema-instance"
                  xmlns:ds="http://www.w3.org/2000/09/xmldsig#"
                  xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"
                  xmlns:xades="http://uri.etsi.org/01903/v1.3.2#"
                  xmlns:xades141="http://uri.etsi.org/01903/v1.4.1#"
                  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  SchemaLocation=""
                  xsi:schemaLocation="dian:gov:co:facturaelectronica:NominaIndividual NominaIndividualElectronicaXSD.xsd">
    <ext:UBLExtensions>
        <ext:UBLExtension>
            <ext:ExtensionContent></ext:ExtensionContent>
        </ext:UBLExtension>
    </ext:UBLExtensions>
    <Periodo FechaIngreso="2023-01-01" FechaLiquidacionInicio="2025-01-01" FechaLiquidacionFin="2025-01-30" TiempoLaborado="30.00" FechaGen="2025-03-04"/>
    <NumeroSecuenciaXML Consecutivo="1" Numero="1" CodigoTrabajador="NT01"/>
    <LugarGeneracionXML Pais="CO" DepartamentoEstado="85" MunicipioCiudad="85001" Idioma="es"/>
    <ProveedorXML NIT="900364032" DV="2" SoftwareID="74244394-4357-4cdb-b3b6-aa0909a0cb8f" SoftwareSC="e43c4ad8807292d298f0c54c3e8486fb60cd14c431140f4ecffdd59037287e0ff0552f23596bd3205b3240786b01bde5"/>
    <CodigoQR>NumNIE=1
        FecNIE=2025-03-04
        HorNIE=21:52:51-05:00
        NitNIE=900364032
        DocEmp=122655531
        ValDev=2000.00
        ValDed=0.00
        ValTol=2000.00
        CUNE=8db613ff24117d391eaa313b8ca63b41020d6cd14f27848df93500b8c6a4717fd3492df6edcaff746c9533ea14e0837c
        URL=https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey=8db613ff24117d391eaa313b8ca63b41020d6cd14f27848df93500b8c6a4717fd3492df6edcaff746c9533ea14e0837c</CodigoQR>
    <InformacionGeneral Version="V1.0: Documento Soporte de Pago de Nómina Electrónica" Ambiente="2" TipoXML="102" CUNE="8db613ff24117d391eaa313b8ca63b41020d6cd14f27848df93500b8c6a4717fd3492df6edcaff746c9533ea14e0837c" EncripCUNE="CUNE-SHA384" FechaGen="2025-03-04" HoraGen="21:52:51-05:00" PeriodoNomina="5" TipoMoneda="COP"/>
    <Notas>Nota 1: Pago realizado correctamente.</Notas>
    <Notas>Nota 2: Pago realizado correctamente.</Notas>
    <Empleador NIT="900364032" DV="2" Pais="CO" DepartamentoEstado="85" MunicipioCiudad="85001" Direccion="Calle 123 # 45 - 67"/>
    <Trabajador TipoTrabajador="01" SubTipoTrabajador="00" AltoRiesgoPension="false" TipoDocumento="13" NumeroDocumento="1122655531" PrimerApellido="vargas" SegundoApellido="figueredo" PrimerNombre="jose" LugarTrabajoPais="CO" LugarTrabajoDepartamentoEstado="85" LugarTrabajoMunicipioCiudad="85001" LugarTrabajoDireccion="Calle 123 # 45 - 67" SalarioIntegral="false" TipoContrato="1" Sueldo="2000.00" OtrosNombres="luis" CodigoTrabajador="NT01"/>
    <Pago Forma="1" Metodo="10"/>
    <FechasPagos>
        <FechaPago>2024-02-05</FechaPago>
    </FechasPagos>
    <Devengados>
        <Basico DiasTrabajados="30" SueldoTrabajado="2000.00"/>
    </Devengados>
    <Deducciones>
        <Salud Porcentaje="0.00" Deduccion="0.00"/>
        <FondoPension Porcentaje="0.00" Deduccion="0.00"/>
    </Deducciones>
    <DevengadosTotal>2000.00</DevengadosTotal>
    <DeduccionesTotal>0.00</DeduccionesTotal>
    <ComprobanteTotal>2000.00</ComprobanteTotal>
</NominaIndividual>';
        $xmlFirmado = $firmaController->firmarXML(
            $xmlSinFirmar,
            $request->input('doctype'),
            $request->input('fechaEmision'),
            $request->input('horaEmision'),
            $request->input('sequence')
        );
    
        return response()->json(['xmlFirmado' => $xmlFirmado]);
    }

}
