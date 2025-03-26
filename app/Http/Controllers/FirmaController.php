<?php

namespace App\Http\Controllers;

use ZipArchive;
use DOMDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FirmaController extends Controller
{
    private static $POLITICA_FIRMA = [
        "name" => "Política de firma para facturas electrónicas de la República de Colombia",
        "url" => "https://facturaelectronica.dian.gov.co/politicadefirma/v2/politicadefirmav2.pdf",
        "digest" => "dMoMvtcG5aIzgYo0tIsSQeVJBDnUnfSOfBpxXrmor0Y="
    ];

    public function firmarXML($xml, $doctype, $fechaEmision, $horaEmision, $sequence){
        try {
            // Obtener el XML sin firmar
            $xmlsinfirma = $xml;
            // Validar si el archivo de certificado existe
            $certificadop12 = storage_path('app/certificado_cliente.p12');
            if (!file_exists($certificadop12)) {
                return response()->json(['error' => 'Certificate file not found'], 500);
            }

            // Datos de configuración
            $clavecertificado = 'Sisoft2024';
            $UUIDv4 = $this->generateUuidV4();

            // Intentar firmar el XML
            $signed = $this->firmar($certificadop12, $clavecertificado, $xmlsinfirma, $UUIDv4, $doctype, $fechaEmision, $horaEmision);

            // Guardar el XML firmado en un archivo
            $filename = 'signed_' . $UUIDv4 . '.xml';

            $companyNit = "900364032";
            $yearXml = date('Y'); // Año calendario

            $formattedNit = str_pad($companyNit, 10, '0', STR_PAD_LEFT);

            $filename = $this->nombre_xml($formattedNit, $yearXml, $sequence, $doctype);
            $filepath = storage_path('app/public/' . $filename);
            file_put_contents($filepath, $signed); // Guarda el archivo XML

            // Define el nombre y ruta del archivo ZIP
            $zipFileName = $this->nombre_zip($formattedNit, $yearXml, $sequence,$doctype);
            $zipFilePath = storage_path('app/public/' . $zipFileName);

            // Crear un nuevo archivo ZIP
            $zip = new ZipArchive;
            if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                $zip->addFile($filepath, $filename); // Agrega el XML al ZIP
                $zip->close();
            //   echo "Archivo ZIP creado con éxito: $zipFilePath";
            } else {
            //   echo "Error al crear el archivo ZIP.";
            }

            // Leer el archivo ZIP y codificarlo en Base64
            $zipBase64 = base64_encode(file_get_contents($zipFilePath));

            $respuesta = [
                'nombre_xml' => $filename,
                'nombre_zip' => $zipFileName,
                'zip_base64' => $zipBase64,
            ];

            return $respuesta;

        } catch (\Exception $e) {
            Log::error('Error processing the XML.', ['exception' => $e->getMessage()]);
            // Capturar excepciones generales y retornar un mensaje de error
            return $respuesta = [
                'error' => 'Error processing the XML: ' . $e->getMessage()
            ];
        }
    }

    public function generateUuidV4() {
        // Genera 16 bytes de datos aleatorios
        $data = random_bytes(16);

        // Ajusta los bytes para cumplir con el formato UUID v4
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Versión 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variante DCE 1.1

        // Formatea el UUID en una cadena
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function nombre_xml($nitCompany, $yearXml, $sequence, $doctype){
        $formattedNit = str_pad($nitCompany, 10, '0', STR_PAD_LEFT);
        $codigoPT = '';
        if ($doctype !='nie'){
            $codigoPT = '000';
        }
        $yearSuffix = substr($yearXml, -2);
        $formattedSequence = str_pad(dechex($sequence), 8, '0', STR_PAD_LEFT);

        // Nombre del archivo
        $xmlFileName = $doctype . $formattedNit . $codigoPT . $yearSuffix . $formattedSequence .'.xml';

        return $xmlFileName;
    }

    public function nombre_zip($nitCompany, $yearXml, $sequence,$doctype = null){
        $formattedNit = str_pad($nitCompany, 10, '0', STR_PAD_LEFT);
        $codigoPT = '';
        if ($doctype !='nie'){
            $codigoPT = '000';
        }
        $yearSuffix = substr($yearXml, -2);
        $formattedSequence = str_pad(dechex($sequence), 8, '0', STR_PAD_LEFT);

        // Nombre del archivo
        $xmlFileName = 'z' . $formattedNit . $codigoPT . $yearSuffix . $formattedSequence .'.zip';

        return $xmlFileName;
    }

    public function base64Encode($strcadena){
        return base64_encode(hash('sha256' , $strcadena, true));
    }

    public function firmar($certificadop12, $clavecertificado, $xmlsinfirma, $UUID, $doctype, $fechaEmision, $horaEmision){
        $pfx = file_get_contents($certificadop12);
        openssl_pkcs12_read($pfx, $key, $clavecertificado);
        $publicKey          = $key["cert"];
        $privateKey         = $key["pkey"];
        $signPolicy         = self::$POLITICA_FIRMA;
        $signatureID        = "xmldsig-".$UUID;
        $Reference0Id       = "xmldsig-".$UUID."-ref0";
        $KeyInfoId          = "xmldsig-".$UUID."-KeyInfo";
        $SignedPropertiesId = "xmldsig-".$UUID. "-signedprops";
        return $this->insertaFirma($xmlsinfirma, $doctype, $publicKey, $privateKey, $signPolicy, $signatureID, $Reference0Id, $KeyInfoId, $SignedPropertiesId, $fechaEmision, $horaEmision);
    }

    public function get_schemas($doctype){
        // obtener como una string los schemas heredados por la etiqueta KeyInfo al momento que el sistema haciendo la validacion del documento (DIAN) canonize el elemento para verificar que el digest sea correcto
        // los schemas heredados por SignedInfo y SignedProperties son los mismos
        $string = '';
        if ($doctype == 'fv' || $doctype == 'ds') {
            $string .= 'xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" ';
        }else{
        if ($doctype == 'nc') {
            $string .= 'xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2" ';
        }
        if ($doctype == 'nd') {
            $string .= 'xmlns="urn:oasis:names:specification:ubl:schema:xsd:DebitNote-2" ';
        }
        if($doctype == 'nie'){
            //$string .= 'xmlns="dian:gov:co:facturaelectronica:NominaIndividual" xmlns:xs="http://www.w3.org/2001/XMLSchema-instance" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2" xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" xmlns:xades141="http://uri.etsi.org/01903/v1.4.1#" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" SchemaLocation="" xsi:schemaLocation="dian:gov:co:facturaelectronica:NominaIndividual NominaIndividualElectronicaXSD.xsd"';
            $string .= 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2" ';
                // $string .= 'http://www.w3.org/2001/XMLSchema-instance ';
            // $string .= 'xmlns:xades141="http://uri.etsi.org/01903/v1.4.1#" ';
            // $string .= 'xmlns:xs="http://www.w3.org/2001/XMLSchema-instance" ';
            // $string .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
            // $string .= 'xmlns="dian:gov:co:facturaelectronica:NominaIndividual" ';
            // $string .= 'xmlns:ds="http://www.w3.org/2000/09/xmldsig#"';
        }
        }


        $string .= 'xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2" xmlns:sts="dian:gov:co:facturaelectronica:Structures-2-1" xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" xmlns:xades141="http://uri.etsi.org/01903/v1.4.1#" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"';

        return $string;
    }

    public function generateSignedProperties($signTime,$certDigest,$certIssuer,$certSerialNumber, $SignedPropertiesId, $signPolicy){
        // version canonicalizada no es necesario volver a hacerlo
        return '<xades:SignedProperties Id="'.$SignedPropertiesId.'">'.
        '<xades:SignedSignatureProperties>'.
            '<xades:SigningTime>'.$signTime.'</xades:SigningTime>' .
            '<xades:SigningCertificate>'.
                '<xades:Cert>'.
                    '<xades:CertDigest>'.
                        '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"></ds:DigestMethod>'.
                        '<ds:DigestValue>'.$certDigest.'</ds:DigestValue>'.
                    '</xades:CertDigest>'.
                    '<xades:IssuerSerial>' .
                        '<ds:X509IssuerName>'.$certIssuer.'</ds:X509IssuerName>'.
                        '<ds:X509SerialNumber>' .$certSerialNumber.'</ds:X509SerialNumber>' .
                    '</xades:IssuerSerial>'.
                '</xades:Cert>'.
            '</xades:SigningCertificate>' .
            '<xades:SignaturePolicyIdentifier>'.
                '<xades:SignaturePolicyId>' .
                    '<xades:SigPolicyId>'.
                        '<xades:Identifier>'.$signPolicy['url'].'</xades:Identifier>'.
                        '<xades:Description>'.$signPolicy['name'].'</xades:Description>'.
                    '</xades:SigPolicyId>'.
                    '<xades:SigPolicyHash>' .
                        '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"></ds:DigestMethod>'.
                        '<ds:DigestValue>'.$signPolicy['digest'].'</ds:DigestValue>'.
                    '</xades:SigPolicyHash>'.
                '</xades:SignaturePolicyId>' .
            '</xades:SignaturePolicyIdentifier>'.
            '<xades:SignerRole>' .
            '<xades:ClaimedRoles>' .
                '<xades:ClaimedRole>supplier</xades:ClaimedRole>' .
            '</xades:ClaimedRoles>' .
            '</xades:SignerRole>' .
        '</xades:SignedSignatureProperties>'.
        '</xades:SignedProperties>';

    }

    public function getKeyInfo($KeyInfoId, $publicKey){
        // version canonicalizada no es necesario volver a hacerlo
        return '<ds:KeyInfo Id="'.$KeyInfoId.'">'.
                '<ds:X509Data>'.
                    '<ds:X509Certificate>'.$this->getCertificate($publicKey).'</ds:X509Certificate>'.
                '</ds:X509Data>'.
                '</ds:KeyInfo>';
    }

    public function getSignedInfo($documentDigest,$kInfoDigest,$SignedPropertiesDigest, $Reference0Id, $KeyInfoId, $SignedPropertiesId){
        // version canonicalizada no es necesario volver a hacerlo
        return '<ds:SignedInfo>'.
                '<ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"></ds:CanonicalizationMethod>'.
                '<ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"></ds:SignatureMethod>'.
                '<ds:Reference Id="'.$Reference0Id.'" URI="">'.
                    '<ds:Transforms><ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"></ds:Transform></ds:Transforms>'.
                    '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"></ds:DigestMethod>'.
                    '<ds:DigestValue>'.$documentDigest.'</ds:DigestValue>'.
                '</ds:Reference>'.
                '<ds:Reference URI="#'.$KeyInfoId.'">'.
                    '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"></ds:DigestMethod>'.
                    '<ds:DigestValue>'.$kInfoDigest.'</ds:DigestValue>'.
                '</ds:Reference>'.
                    '<ds:Reference Type="http://uri.etsi.org/01903#SignedProperties" URI="#'.$SignedPropertiesId.'">'.
                    '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"></ds:DigestMethod>'.
                    '<ds:DigestValue>'.$SignedPropertiesDigest.'</ds:DigestValue>'.
                '</ds:Reference>'.
            '</ds:SignedInfo>';
    }

    public function getIssuer($issuer){
        $certIssuer = array();
        foreach ($issuer as $item => $value){
            $certIssuer[] = $item . '=' . $value;
        }
        $certIssuer = implode(', ', array_reverse($certIssuer));
        return $certIssuer;
    }

    public function getCertificate($publicKey){
        openssl_x509_export($publicKey, $publicPEM);
        $publicPEM = str_replace("-----BEGIN CERTIFICATE-----", "", $publicPEM);
        $publicPEM = str_replace("-----END CERTIFICATE-----", "", $publicPEM);
        $publicPEM = str_replace("\r", "", str_replace("\n", "", $publicPEM));
        return $publicPEM;
    }

    public function insertaFirma($xml, $doctype, $publicKey, $privateKey, $signPolicy, $signatureID, $Reference0Id, $KeyInfoId, $SignedPropertiesId, $fechaEmision, $horaEmision){
        $d = new DOMDocument('1.0','UTF-8');
        $d->loadXML($xml);
        $canonicalXML = $d->C14N();
        $documentDigest = base64_encode(hash('sha256', $canonicalXML, true));
    //   $signTime = date('Y-m-d\TH:i:s-05:00');
    $signTime = $fechaEmision . 'T' . $horaEmision;

        $certData   = openssl_x509_parse($publicKey);
        $certDigest = base64_encode(openssl_x509_fingerprint($publicKey, "sha256", true));
        $certSerialNumber = $certData['serialNumber'];
        $certIssuer = $this->getIssuer($certData['issuer']);

        $SignedProperties = $this->generateSignedProperties($signTime,$certDigest,$certIssuer,$certSerialNumber, $SignedPropertiesId, $signPolicy);
        $SignedPropertiesWithSchemas = str_replace('<xades:SignedProperties', '<xades:SignedProperties '.$this->get_schemas($doctype), $SignedProperties);
        $SignedPropertiesDigest = $this->base64Encode($SignedPropertiesWithSchemas);

        $KeyInfo = $this->getKeyInfo($KeyInfoId, $publicKey);
        $keyInfoWithShemas = str_replace('<ds:KeyInfo', '<ds:KeyInfo '.$this->get_schemas($doctype), $KeyInfo);
        $kInfoDigest = $this->base64Encode($keyInfoWithShemas);

        $signedInfo = $this->getSignedInfo($documentDigest,$kInfoDigest,$SignedPropertiesDigest, $Reference0Id, $KeyInfoId, $SignedPropertiesId);
        $SignedInfoWithSchemas = str_replace('<ds:SignedInfo', '<ds:SignedInfo '.$this->get_schemas($doctype), $signedInfo);

        $algo = "SHA256";
        openssl_sign($SignedInfoWithSchemas, $signatureResult, $privateKey, $algo);
        $signatureResult = base64_encode($signatureResult);


        $s = '<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Id="'.$signatureID.'">'. $signedInfo. '<ds:SignatureValue>'.$signatureResult.'</ds:SignatureValue>'.$KeyInfo.'<ds:Object><xades:QualifyingProperties Target="#'.$signatureID.'">'.$SignedProperties.'</xades:QualifyingProperties></ds:Object></ds:Signature>';
        //$s = '<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Id="'.$signatureID.'">'. $signedInfo. '<ds:SignatureValue Id="'.$signatureID.'-sigvalue">'.$signatureResult.'</ds:SignatureValue>'.$KeyInfo.'<ds:Object><xades:QualifyingProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" xmlns:xades141="http://uri.etsi.org/01903/v1.4.1#" Target="#'.$signatureID.'">'.$SignedProperties.'</xades:QualifyingProperties></ds:Object></ds:Signature>';
        //echo $s;
        $search = '<ext:ExtensionContent></ext:ExtensionContent>';
        $replace = '<ext:ExtensionContent>'.$s."</ext:ExtensionContent>";
        $signed = str_replace($search, $replace, $canonicalXML);
        return $signed;

    }
}
