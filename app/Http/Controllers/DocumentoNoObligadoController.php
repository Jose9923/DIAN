<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\SoapController;
use App\Http\Controllers\FirmaController;
use App\Http\Controllers\XmlDocumentoNoObligadoController;
use Illuminate\Support\Facades\Log;


class DocumentoNoObligadoController extends Controller
{
    public function crearNoObligado(Request $request){
        $doctype = $request->doctype;
        $xmlController = new XmlDocumentoNoObligadoController();
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

    public function generarCUDS(Request $request){
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
        $SoftwarePin2 = '12345';
        $tipoAmbiente = '2';

        $NumDS = $facturaId;
        $FecDS = $fechaEmision;
        $HorDS = $horaEmision;
        $ValDS = $valorTotal;
        $CodImp = '01';
        $ValImp = '0.00';
        $ValTot = $valorTotal;
        $NumSNO = $companyNit;
        $NITABS = $cedulaCliente;
        $SoftwarePin = $SoftwarePin2;
        $tipoAmbiente1 = $tipoAmbiente;

        $CUDS = $NumDS . $FecDS . $HorDS . $ValDS . $CodImp . $ValImp . $ValTot . $NumSNO . $NITABS . $SoftwarePin . $tipoAmbiente1;

        $UUID = hash('sha384', $CUDS);

        $formattedNit = str_pad($companyNit, 10, '0', STR_PAD_LEFT);
        $sequence = $preFactura;
        $yearXml = date('Y'); // Año calendario

        $filename = $this->nombre_xml($formattedNit, $yearXml, $sequence);
        $zipFileName = $this->nombre_zip($formattedNit, $yearXml, $sequence);

        return response()->json([
            'CUDS' => $UUID,
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
        $xmlFileName = 'ds' . $formattedNit . $codigoPT . $yearSuffix . $formattedSequence .'.xml';

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
}
