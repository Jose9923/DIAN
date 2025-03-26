<?php

namespace App\Http\Controllers\Nomina;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Nomina\XmlNominaController;
use App\Http\Controllers\SoapController;
use App\Http\Controllers\FirmaController;
use Illuminate\Support\Facades\Log;

class NominaController extends Controller
{
    public function crearNominaElectronica(Request $request)
    {
        $doctype = $request->doctype;
        $xmlController = new XmlNominaController();
        $xmlResponse = $xmlController->crearXML($request);
        // Extraer los datos del JsonResponse
        $xmlData = $xmlResponse->getData(); // Obtiene los datos como un objeto
        $xmlSinFirmar = $xmlData->xmlSinFirmar;
        $qrlink = $xmlData->qrlink;
        //return $xmlSinFirmar;

        // Firmar el XML
        $firmaController = new FirmaController();
        $xmlFirmado = $firmaController->firmarXML(
            $xmlSinFirmar,
            $doctype,
            $xmlData->fechaEmision,
            $xmlData->horaEmision,
            $xmlData->sequence
        );
    
        // Enviar la petición SOAP
        $soapController = new SoapController();
        $response = $soapController->enviarPeticion(
            $xmlFirmado['nombre_zip'],
            $xmlFirmado['zip_base64'],
            $request->tipoAmbiente,$request->doctype
        );

        if ($request->tipoAmbiente == '2' && $request->doctype == 'nie') {
            // Procesar la respuesta para obtener el ZipKey
            $zipKey = $this->procesarRespuestaSoap($response);
            
            if ($zipKey) {
                sleep(10);
                // Si se obtuvo el ZipKey, enviar la petición con el ZipKey
                $response = $soapController->enviarPeticionZipKey($zipKey);
                $respuestaDian = $this->procesarRespuestaZipKey($response);
            } else {
                // Manejar el caso en que no se pudo obtener el ZipKey
                Log::error('No se pudo obtener el ZipKey de la respuesta SOAP.');
            }
        } else{
            $respuestaDian = $this->procesarRespuestaNomina($response);
        }

        // Devolver la respuesta final
        return response()->json([
            'qrlink' => $qrlink,
            'cune' => $xmlData->cune,
            'factura_id' => $xmlData->factura_id,
            'fecha_emision' => $xmlData->fechaEmision,
            'nombre xml' => $xmlFirmado['nombre_xml'],
            'nombre_zip' => $xmlFirmado['nombre_zip'],
            'zip_base64' => $xmlFirmado['zip_base64'],
            'response' => $response,
            'respuesta_dian' => $respuestaDian,
        ], 200);
    }
    public function generarCUNE(Request $request){
        $inicioFactura = $request->secuence;
        $from = '0000';
        $prefix = 'N';
        $preFactura = $from + $inicioFactura;
        $nominaId = $prefix . $preFactura;
        $SoftwareId = 'f7dacfe9-f922-416a-91db-940fd7b4c0b';//'7b2c871b-c6a3-4eb9-822e-f3fec297dcf9';//
        $SoftwarePin = '123456';
        $fechaEmision = $request->fecha_emision;
        $horaEmision = $request->hora_emision . '-05:00';
        $totalDevengos = $request->totalDevengos;
        $totalDeducciones = $request->totalDeducciones;
        $totalPagado = $request->totalPagado;
        $nitEmpleado = $request->nitEmpleado;
        $tipoXml = $request->tipoXml;
        $tipoAmbiente = '2';
        $nitSisoft = "900364032";

        $NumNE = $nominaId;
        $FecNE = $fechaEmision;
        $HorNE = $horaEmision;
        $ValDev = $totalDevengos;
        $ValDed = $totalDeducciones;
        $ValTolNE = $totalPagado;//devengo-deducciones
        $NitNE = $nitSisoft;
        $DocEmp = $nitEmpleado;
        $TipoXML = $tipoXml;
        $Software_Pin = $SoftwarePin;
        $TipAmb = $tipoAmbiente;

        $CUNE = $NumNE . $FecNE . $HorNE . $ValDev . $ValDed . $ValTolNE . $NitNE . $DocEmp . $TipoXML . $Software_Pin . $TipAmb;

        $UUID = hash('sha384', $CUNE);

        $SoftwareSecurityCode = hash('sha384', $SoftwareId . $SoftwarePin . $nominaId);

        $formattedNit = str_pad($nitSisoft, 10, '0', STR_PAD_LEFT);
        $sequence = $preFactura;
        $yearXml = date('Y'); // Año calendario

        $filename = $this->nombre_xml($formattedNit, $yearXml, $sequence);

        $zipFileName = $this->nombre_zip($formattedNit, $yearXml, $sequence);

        return response()->json([
            'huella_software' => $SoftwareSecurityCode,
            'CUNE' => $UUID,
            'nombre_xml' => $filename,
            'nombre_zip' => $zipFileName,
        ], 200);
    }

    function nombre_xml($nitObligado, $yearXml, $sequence){//nit del sujeto obligado no se si es la empresa o el empleado
        $formattedNit = str_pad($nitObligado, 10, '0', STR_PAD_LEFT);
        $yearSuffix = substr($yearXml, -2);
        $formattedSequence = str_pad(dechex($sequence), 8, '0', STR_PAD_LEFT);

        // Nombre del archivo
        $xmlFileName = 'nie' . $formattedNit . $yearSuffix . $formattedSequence .'.xml';

        return $xmlFileName;
    }

    function nombre_zip($nitObligado, $yearXml, $sequence){//nit del sujeto obligado no se si es la empresa o el empleado
        $formattedNit = str_pad($nitObligado, 10, '0', STR_PAD_LEFT);
        $codigoPT = '000';
        $yearSuffix = substr($yearXml, -2);
        $formattedSequence = str_pad(dechex($sequence), 8, '0', STR_PAD_LEFT);

        // Nombre del archivo
        $xmlFileName = 'z' . $formattedNit . $yearSuffix . $formattedSequence .'.zip';

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

    public function procesarRespuestaNomina($response)
    {
        try {
            // Convertir la cadena XML en un objeto SimpleXMLElement
            $xml = new \SimpleXMLElement($response);
    
            // Obtener los namespaces del XML
            $namespaces = $xml->getNamespaces(true);
    
            // Acceder al cuerpo del mensaje
            $body = $xml->children($namespaces['s'])->Body;
            $sendNominaSyncResponse = $body->children($namespaces[''])->SendNominaSyncResponse;
            $sendNominaSyncResult = $sendNominaSyncResponse->children($namespaces[''])->SendNominaSyncResult;
    
            // Obtener los mensajes de error
            $errorMessages = $sendNominaSyncResult->children($namespaces['b'])->ErrorMessage;
    
            $errors = [];
            if ($errorMessages) {
                foreach ($errorMessages->children($namespaces['c']) as $error) {
                    $errors[] = (string) $error;
                }
            }
    
            // Extraer otros datos importantes
            $isValid = (string) $sendNominaSyncResult->children($namespaces['b'])->IsValid;
            $statusCode = (string) $sendNominaSyncResult->children($namespaces['b'])->StatusCode;
            $statusDescription = (string) $sendNominaSyncResult->children($namespaces['b'])->StatusDescription;
            $statusMessage = (string) $sendNominaSyncResult->children($namespaces['b'])->StatusMessage;
            $xmlDocumentKey = (string) $sendNominaSyncResult->children($namespaces['b'])->XmlDocumentKey;
            $xmlFileName = (string) $sendNominaSyncResult->children($namespaces['b'])->XmlFileName;
    
            // Loguear la información
            Log::info("Nómina procesada:", [
                'IsValid' => $isValid,
                'StatusCode' => $statusCode,
                'StatusDescription' => $statusDescription,
                'StatusMessage' => $statusMessage,
                'XmlDocumentKey' => $xmlDocumentKey,
                'XmlFileName' => $xmlFileName,
                'Errors' => $errors,
            ]);
    
            return response()->json([
                'success' => true,
                'is_valid' => $isValid,
                'status_code' => $statusCode,
                'status_description' => $statusDescription,
                'status_message' => $statusMessage,
                'xml_document_key' => $xmlDocumentKey,
                'xml_file_name' => $xmlFileName,
                'errors' => $errors,
            ]);
    
        } catch (\Exception $e) {
            Log::error("Error procesando la respuesta XML: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al procesar la nómina'], 500);
        }
    }

    public function procesarRespuestaSoap($soapResponse)
    {
        try {
            // Cargar el XML en un objeto SimpleXMLElement
            $xml = new \SimpleXMLElement($soapResponse);

            // Registrar los namespaces para poder acceder a los elementos correctamente
            $namespaces = $xml->getNamespaces(true);

            // Registrar el namespace del cuerpo del mensaje
            $body = $xml->children($namespaces['s'])->Body;
            $response = $body->children(null, true)->SendTestSetAsyncResponse;
            $result = $response->SendTestSetAsyncResult;

            // Registrar el namespace de 'b' para acceder a los elementos dentro de 'SendTestSetAsyncResult'
            $resultNamespaces = $result->getNamespaces(true);
            $result->registerXPathNamespace('b', $resultNamespaces['b']);

            // Obtener el valor de ZipKey
            $zipKey = (string)$result->children($resultNamespaces['b'])->ZipKey;

            return $zipKey;
        } catch (\Exception $e) {
            // Loggear el error si ocurre algún problema al procesar el XML
            Log::error('Error al procesar la respuesta SOAP: ' . $e->getMessage());
            return null;
        }
    }
    public function procesarRespuestaZipKey($response)
    {
        try {
            // Convertir la cadena XML en un objeto SimpleXMLElement
            $xml = new \SimpleXMLElement($response);
    
            // Obtener los namespaces del XML
            $namespaces = $xml->getNamespaces(true);
    
            // Acceder al cuerpo del mensaje
            $body = $xml->children($namespaces['s'])->Body;
            $getStatusZipResponse = $body->children($namespaces[''])->GetStatusZipResponse;
            $getStatusZipResult = $getStatusZipResponse->children($namespaces[''])->GetStatusZipResult;
    
            // Acceder a la respuesta de la DIAN
            $dianResponse = $getStatusZipResult->children($namespaces['b'])->DianResponse;
    
            // Extraer los datos importantes
            $isValid = (string) $dianResponse->children($namespaces['b'])->IsValid;
            $statusCode = (string) $dianResponse->children($namespaces['b'])->StatusCode;
            $statusDescription = (string) $dianResponse->children($namespaces['b'])->StatusDescription;
            $statusMessage = (string) $dianResponse->children($namespaces['b'])->StatusMessage;
            $xmlBase64Bytes = (string) $dianResponse->children($namespaces['b'])->XmlBase64Bytes;
            $xmlBytes = (string) $dianResponse->children($namespaces['b'])->XmlBytes;
            $xmlDocumentKey = (string) $dianResponse->children($namespaces['b'])->XmlDocumentKey;
            $xmlFileName = (string) $dianResponse->children($namespaces['b'])->XmlFileName;
    
            // Extraer el mensaje de error si existe
            $errorMessage = '';
            if (isset($dianResponse->children($namespaces['b'])->ErrorMessage)) {
                $errorMessage = (string) $dianResponse->children($namespaces['b'])->ErrorMessage->children($namespaces['c'])->string;
            }
    
            // Loguear la información
            Log::info("Respuesta de GetStatusZip procesada:", [
                'IsValid' => $isValid,
                'StatusCode' => $statusCode,
                'StatusDescription' => $statusDescription,
                'StatusMessage' => $statusMessage,
                //'XmlBase64Bytes' => $xmlBase64Bytes,
                'XmlBytes' => $xmlBytes,
                'XmlDocumentKey' => $xmlDocumentKey,
                'XmlFileName' => $xmlFileName,
                'ErrorMessage' => $errorMessage,
            ]);
    
            return response()->json([
                'success' => true,
                'is_valid' => $isValid,
                'status_code' => $statusCode,
                'status_description' => $statusDescription,
                'status_message' => $statusMessage,
                //'xml_base64_bytes' => $xmlBase64Bytes,
                'xml_bytes' => $xmlBytes,
                'xml_document_key' => $xmlDocumentKey,
                'xml_file_name' => $xmlFileName,
                'error_message' => $errorMessage,
            ]);
    
        } catch (\Exception $e) {
            Log::error("Error procesando la respuesta XML de GetStatusZip: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al procesar la respuesta de GetStatusZip'], 500);
        }
    }
}
