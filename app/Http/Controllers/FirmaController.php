<?php

namespace App\Http\Controllers;

use ZipArchive;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FirmaController extends Controller
{
    private static $POLITICA_FIRMA = [
        "name" => "Política de firma para facturas electrónicas de la República de Colombia",
        "url" => "https://facturaelectronica.dian.gov.co/politicadefirma/v2/politicadefirmav2.pdf",
        "digest" => "dMoMvtcG5aIzgYo0tIsSQeVJBDnUnfSOfBpxXrmor0Y="
    ];

    public function firmarXML($xml, $doctype, $fechaEmision, $horaEmision, $sequence)
    {
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

            // Determinar si necesitamos agregar el placeholder para firma
            $xmlConPlaceholder = $this->agregarPlaceholderFirma($xmlsinfirma);

            // Intentar firmar el XML
            $signed = $this->firmarConXmlSecLib($certificadop12, $clavecertificado, $xmlConPlaceholder, $UUIDv4, $doctype, $fechaEmision, $horaEmision);

            // Guardar el XML firmado en un archivo
            $filename = 'signed_' . $UUIDv4 . '.xml';

            $companyNit = "900364032";
            $yearXml = date('Y'); // Año calendario

            $formattedNit = str_pad($companyNit, 10, '0', STR_PAD_LEFT);

            $filename = $this->nombre_xml($formattedNit, $yearXml, $sequence, $doctype);
            $filepath = storage_path('app/public/' . $filename);
            file_put_contents($filepath, $signed); // Guarda el archivo XML

            // Define el nombre y ruta del archivo ZIP
            $zipFileName = $this->nombre_zip($formattedNit, $yearXml, $sequence, $doctype);
            $zipFilePath = storage_path('app/public/' . $zipFileName);

            // Crear un nuevo archivo ZIP
            $zip = new ZipArchive;
            if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                $zip->addFile($filepath, $filename); // Agrega el XML al ZIP
                $zip->close();
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

    /**
     * Agrega placeholder para firma en el documento XML
     * 
     * @param string $xml Contenido XML
     * @return string XML con placeholder agregado
     */
    public function agregarPlaceholderFirma($xml)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);

        // Registrar el namespace ext para poder realizar las búsquedas XPath
        $xpath->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');

        // Obtenemos el nodo raíz
        $root = $dom->documentElement;

        // Verificamos si existe el nodo UBLExtensions
        $extensions = $xpath->query('//ext:UBLExtensions')->item(0);

        if (!$extensions) {
            // Si no existe, lo creamos
            $extensions = $dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2', 'ext:UBLExtensions');
            $extension = $dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2', 'ext:UBLExtension');
            $extensionContent = $dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2', 'ext:ExtensionContent');

            $extension->appendChild($extensionContent);
            $extensions->appendChild($extension);

            // Añadimos al inicio del documento
            if ($root->firstChild) {
                $root->insertBefore($extensions, $root->firstChild);
            } else {
                $root->appendChild($extensions);
            }

            return $dom->saveXML();
        }

        // Verificamos si existe al menos una extensión
        $extension = $xpath->query('//ext:UBLExtensions/ext:UBLExtension[last()]')->item(0);

        if (!$extension) {
            // Si no existe, lo creamos
            $extension = $dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2', 'ext:UBLExtension');
            $extensionContent = $dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2', 'ext:ExtensionContent');

            $extension->appendChild($extensionContent);
            $extensions->appendChild($extension);

            return $dom->saveXML();
        }

        // Verificamos si existe el nodo ExtensionContent
        $extensionContent = $xpath->query('//ext:UBLExtensions/ext:UBLExtension[last()]/ext:ExtensionContent')->item(0);

        if (!$extensionContent) {
            // Si no existe, lo creamos
            $extensionContent = $dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2', 'ext:ExtensionContent');
            $extension->appendChild($extensionContent);

            return $dom->saveXML();
        }

        // Si el extensionContent tiene contenido, creamos una nueva extensión
        if ($extensionContent->hasChildNodes() || trim($extensionContent->nodeValue) != '') {
            $extension = $dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2', 'ext:UBLExtension');
            $extensionContent = $dom->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2', 'ext:ExtensionContent');

            $extension->appendChild($extensionContent);
            $extensions->appendChild($extension);

            return $dom->saveXML();
        }

        // Si el nodo tiene comentarios, los eliminamos
        foreach ($extensionContent->childNodes as $child) {
            if ($child->nodeType === XML_COMMENT_NODE) {
                $extensionContent->removeChild($child);
            }
        }

        return $dom->saveXML();
    }

    /**
     * Verifica si el XML ya tiene la estructura para firmar
     * 
     * @param string $xml Contenido XML
     * @return bool True si ya tiene estructura, False si no
     */
    public function tieneEstructuraParaFirma($xml)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');

        // Buscar el nodo ExtensionContent vacío
        $nodes = $xpath->query('//ext:UBLExtensions/ext:UBLExtension/ext:ExtensionContent[not(node())]');

        return $nodes->length > 0;
    }

    /**
     * Alternativa de firmado usando XMLSecLibs
     * 
     * @param string $certificadop12 Ruta al certificado
     * @param string $clavecertificado Clave del certificado
     * @param string $xml XML a firmar
     * @param string $UUID UUID para identificadores
     * @param string $doctype Tipo de documento
     * @param string $fechaEmision Fecha de emisión
     * @param string $horaEmision Hora de emisión
     * @return string XML firmado
     */
    public function firmarConXmlSecLib($certificadop12, $clavecertificado, $xml, $UUID, $doctype, $fechaEmision, $horaEmision)
    {
        // Esta implementación requiere la biblioteca XMLSecLibs
        // composer require robrichards/xmlseclibs

        // Verificar si la biblioteca está instalada
        if (!class_exists('\\RobRichards\\XMLSecLibs\\XMLSecurityDSig')) {
            throw new \Exception('XMLSecLibs no está instalado. Instálelo con: composer require robrichards/xmlseclibs');
        }

        $pfx = file_get_contents($certificadop12);
        openssl_pkcs12_read($pfx, $key, $clavecertificado);
        $publicKey = $key["cert"];
        $privateKey = $key["pkey"];

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($xml);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');

        // Buscar el nodo ExtensionContent
        $extensionContentNodes = $xpath->query('//ext:UBLExtensions/ext:UBLExtension[last()]/ext:ExtensionContent');
        if ($extensionContentNodes->length == 0) {
            throw new \Exception('No se encontró el nodo ExtensionContent para la firma');
        }

        $extensionContentNode = $extensionContentNodes->item(0);

        // Crear objeto de firma
        $objDSig = new \RobRichards\XMLSecLibs\XMLSecurityDSig();
        $objDSig->setCanonicalMethod(\RobRichards\XMLSecLibs\XMLSecurityDSig::EXC_C14N);

        // ID para la firma
        $signatureId = "xmldsig-" . $UUID;
        $objDSig->addReference(
            $dom,
            \RobRichards\XMLSecLibs\XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            ['force_uri' => true, 'id_name' => 'Id', 'overwrite' => false]
        );

        // Configurar clave para la firma
        $objKey = new \RobRichards\XMLSecLibs\XMLSecurityKey(
            \RobRichards\XMLSecLibs\XMLSecurityKey::RSA_SHA256,
            ['type' => 'private']
        );
        $objKey->loadKey($privateKey);

        // Añadir información de clave
        $objDSig->add509Cert($publicKey);

        // Añadir política de firma
        $signTimeNode = $dom->createElementNS(
            'http://uri.etsi.org/01903/v1.3.2#',
            'xades:SigningTime',
            $fechaEmision . 'T' . $horaEmision
        );

        // Política
        $signaturePolicyNode = $dom->createElementNS(
            'http://uri.etsi.org/01903/v1.3.2#',
            'xades:SignaturePolicyIdentifier'
        );
        $policyIdNode = $dom->createElementNS(
            'http://uri.etsi.org/01903/v1.3.2#',
            'xades:SignaturePolicyId'
        );

        // Configurar URL y hash de la política
        $sigPolicyId = $dom->createElementNS(
            'http://uri.etsi.org/01903/v1.3.2#',
            'xades:SigPolicyId'
        );
        $identifier = $dom->createElementNS(
            'http://uri.etsi.org/01903/v1.3.2#',
            'xades:Identifier',
            self::$POLITICA_FIRMA['url']
        );
        $sigPolicyId->appendChild($identifier);
        $policyIdNode->appendChild($sigPolicyId);

        // Hash de la política
        $sigPolicyHash = $dom->createElementNS(
            'http://uri.etsi.org/01903/v1.3.2#',
            'xades:SigPolicyHash'
        );
        $digestMethod = $dom->createElementNS(
            'http://www.w3.org/2000/09/xmldsig#',
            'ds:DigestMethod'
        );
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $sigPolicyHash->appendChild($digestMethod);

        $digestValue = $dom->createElementNS(
            'http://www.w3.org/2000/09/xmldsig#',
            'ds:DigestValue',
            self::$POLITICA_FIRMA['digest']
        );
        $sigPolicyHash->appendChild($digestValue);

        $policyIdNode->appendChild($sigPolicyHash);
        $signaturePolicyNode->appendChild($policyIdNode);

        // Rol del firmante
        $signerRole = $dom->createElementNS(
            'http://uri.etsi.org/01903/v1.3.2#',
            'xades:SignerRole'
        );
        $claimedRoles = $dom->createElementNS(
            'http://uri.etsi.org/01903/v1.3.2#',
            'xades:ClaimedRoles'
        );
        $claimedRole = $dom->createElementNS(
            'http://uri.etsi.org/01903/v1.3.2#',
            'xades:ClaimedRole',
            'supplier'
        );
        $claimedRoles->appendChild($claimedRole);
        $signerRole->appendChild($claimedRoles);

        // Firma el documento
        $objDSig->sign($objKey, $extensionContentNode);

        // Añade propiedades a la firma
        $qualifyingProperties = $dom->getElementsByTagNameNS(
            'http://uri.etsi.org/01903/v1.3.2#',
            'QualifyingProperties'
        )->item(0);

        if ($qualifyingProperties) {
            $signedProps = $dom->getElementsByTagNameNS(
                'http://uri.etsi.org/01903/v1.3.2#',
                'SignedProperties'
            )->item(0);

            if ($signedProps) {
                $signedSigProps = $dom->getElementsByTagNameNS(
                    'http://uri.etsi.org/01903/v1.3.2#',
                    'SignedSignatureProperties'
                )->item(0);

                if ($signedSigProps) {
                    $signedSigProps->appendChild($signTimeNode);
                    $signedSigProps->appendChild($signaturePolicyNode);
                    $signedSigProps->appendChild($signerRole);
                }
            }
        }

        return $dom->saveXML();
    }

    public function generateUuidV4()
    {
        // Genera 16 bytes de datos aleatorios
        $data = random_bytes(16);

        // Ajusta los bytes para cumplir con el formato UUID v4
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Versión 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variante DCE 1.1

        // Formatea el UUID en una cadena
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function nombre_xml($nitCompany, $yearXml, $sequence, $doctype)
    {
        $formattedNit = str_pad($nitCompany, 10, '0', STR_PAD_LEFT);
        $codigoPT = '';
        if ($doctype != 'nie') {
            $codigoPT = '000';
        }
        $yearSuffix = substr($yearXml, -2);
        $formattedSequence = str_pad(dechex($sequence), 8, '0', STR_PAD_LEFT);

        // Nombre del archivo
        $xmlFileName = $doctype . $formattedNit . $codigoPT . $yearSuffix . $formattedSequence . '.xml';

        return $xmlFileName;
    }

    public function nombre_zip($nitCompany, $yearXml, $sequence, $doctype = null)
    {
        $formattedNit = str_pad($nitCompany, 10, '0', STR_PAD_LEFT);
        $codigoPT = '';
        if ($doctype != 'nie') {
            $codigoPT = '000';
        }
        $yearSuffix = substr($yearXml, -2);
        $formattedSequence = str_pad(dechex($sequence), 8, '0', STR_PAD_LEFT);

        // Nombre del archivo
        $xmlFileName = 'z' . $formattedNit . $codigoPT . $yearSuffix . $formattedSequence . '.zip';

        return $xmlFileName;
    }

    public function base64Encode($strcadena)
    {
        return base64_encode(hash('sha256', $strcadena, true));
    }

    public function firmar($certificadop12, $clavecertificado, $xmlsinfirma, $UUID, $doctype, $fechaEmision, $horaEmision)
    {
        // Verificar si el XML ya tiene la estructura para firmar
        if (!$this->tieneEstructuraParaFirma($xmlsinfirma)) {
            $xmlsinfirma = $this->agregarPlaceholderFirma($xmlsinfirma);
        }

        $pfx = file_get_contents($certificadop12);
        openssl_pkcs12_read($pfx, $key, $clavecertificado);
        $publicKey          = $key["cert"];
        $privateKey         = $key["pkey"];
        $signPolicy         = self::$POLITICA_FIRMA;
        $signatureID        = "xmldsig-" . $UUID;
        $Reference0Id       = "xmldsig-" . $UUID . "-ref0";
        $KeyInfoId          = "xmldsig-" . $UUID . "-KeyInfo";
        $SignedPropertiesId = "xmldsig-" . $UUID . "-signedprops";
        return $this->insertaFirma($xmlsinfirma, $doctype, $publicKey, $privateKey, $signPolicy, $signatureID, $Reference0Id, $KeyInfoId, $SignedPropertiesId, $fechaEmision, $horaEmision);
    }

    public function get_schemas($doctype)
    {
        // obtener como una string los schemas heredados por la etiqueta KeyInfo al momento que el sistema haciendo la validacion del documento (DIAN) canonize el elemento para verificar que el digest sea correcto
        // los schemas heredados por SignedInfo y SignedProperties son los mismos
        $string = '';
        if ($doctype == 'fv' || $doctype == 'ds') {
            $string .= 'xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" ';
        } else {
            if ($doctype == 'nc') {
                $string .= 'xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2" ';
            }
            if ($doctype == 'nd') {
                $string .= 'xmlns="urn:oasis:names:specification:ubl:schema:xsd:DebitNote-2" ';
            }
            if ($doctype == 'nie') {
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

    public function generateSignedProperties($signTime, $certDigest, $certIssuer, $certSerialNumber, $SignedPropertiesId, $signPolicy)
    {
        // version canonicalizada no es necesario volver a hacerlo
        return '<xades:SignedProperties Id="' . $SignedPropertiesId . '">' .
            '<xades:SignedSignatureProperties>' .
            '<xades:SigningTime>' . $signTime . '</xades:SigningTime>' .
            '<xades:SigningCertificate>' .
            '<xades:Cert>' .
            '<xades:CertDigest>' .
            '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"></ds:DigestMethod>' .
            '<ds:DigestValue>' . $certDigest . '</ds:DigestValue>' .
            '</xades:CertDigest>' .
            '<xades:IssuerSerial>' .
            '<ds:X509IssuerName>' . $certIssuer . '</ds:X509IssuerName>' .
            '<ds:X509SerialNumber>' . $certSerialNumber . '</ds:X509SerialNumber>' .
            '</xades:IssuerSerial>' .
            '</xades:Cert>' .
            '</xades:SigningCertificate>' .
            '<xades:SignaturePolicyIdentifier>' .
            '<xades:SignaturePolicyId>' .
            '<xades:SigPolicyId>' .
            '<xades:Identifier>' . $signPolicy['url'] . '</xades:Identifier>' .
            '<xades:Description>' . $signPolicy['name'] . '</xades:Description>' .
            '</xades:SigPolicyId>' .
            '<xades:SigPolicyHash>' .
            '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"></ds:DigestMethod>' .
            '<ds:DigestValue>' . $signPolicy['digest'] . '</ds:DigestValue>' .
            '</xades:SigPolicyHash>' .
            '</xades:SignaturePolicyId>' .
            '</xades:SignaturePolicyIdentifier>' .
            '<xades:SignerRole>' .
            '<xades:ClaimedRoles>' .
            '<xades:ClaimedRole>supplier</xades:ClaimedRole>' .
            '</xades:ClaimedRoles>' .
            '</xades:SignerRole>' .
            '</xades:SignedSignatureProperties>' .
            '</xades:SignedProperties>';
    }

    public function getKeyInfo($KeyInfoId, $publicKey)
    {
        // version canonicalizada no es necesario volver a hacerlo
        return '<ds:KeyInfo Id="' . $KeyInfoId . '">' .
            '<ds:X509Data>' .
            '<ds:X509Certificate>' . $this->getCertificate($publicKey) . '</ds:X509Certificate>' .
            '</ds:X509Data>' .
            '</ds:KeyInfo>';
    }

    public function getSignedInfo($documentDigest, $kInfoDigest, $SignedPropertiesDigest, $Reference0Id, $KeyInfoId, $SignedPropertiesId)
    {
        // version canonicalizada no es necesario volver a hacerlo
        return '<ds:SignedInfo>' .
            '<ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"></ds:CanonicalizationMethod>' .
            '<ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"></ds:SignatureMethod>' .
            '<ds:Reference Id="' . $Reference0Id . '" URI="">' .
            '<ds:Transforms><ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"></ds:Transform></ds:Transforms>' .
            '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"></ds:DigestMethod>' .
            '<ds:DigestValue>' . $documentDigest . '</ds:DigestValue>' .
            '</ds:Reference>' .
            '<ds:Reference URI="#' . $KeyInfoId . '">' .
            '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"></ds:DigestMethod>' .
            '<ds:DigestValue>' . $kInfoDigest . '</ds:DigestValue>' .
            '</ds:Reference>' .
            '<ds:Reference Type="http://uri.etsi.org/01903#SignedProperties" URI="#' . $SignedPropertiesId . '">' .
            '<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"></ds:DigestMethod>' .
            '<ds:DigestValue>' . $SignedPropertiesDigest . '</ds:DigestValue>' .
            '</ds:Reference>' .
            '</ds:SignedInfo>';
    }

    public function getIssuer($issuer)
    {
        $certIssuer = array();
        foreach ($issuer as $item => $value) {
            $certIssuer[] = $item . '=' . $value;
        }
        $certIssuer = implode(', ', array_reverse($certIssuer));
        return $certIssuer;
    }

    public function getCertificate($publicKey)
    {
        openssl_x509_export($publicKey, $publicPEM);
        $publicPEM = str_replace("-----BEGIN CERTIFICATE-----", "", $publicPEM);
        $publicPEM = str_replace("-----END CERTIFICATE-----", "", $publicPEM);
        $publicPEM = str_replace("\r", "", str_replace("\n", "", $publicPEM));
        return $publicPEM;
    }

    public function insertaFirma($xml, $doctype, $publicKey, $privateKey, $signPolicy, $signatureID, $Reference0Id, $KeyInfoId, $SignedPropertiesId, $fechaEmision, $horaEmision)
    {
        $d = new DOMDocument('1.0', 'UTF-8');
        $d->loadXML($xml);
        $canonicalXML = $d->C14N();
        $documentDigest = base64_encode(hash('sha256', $canonicalXML, true));
        $signTime = $fechaEmision . 'T' . $horaEmision;

        $certData   = openssl_x509_parse($publicKey);
        $certDigest = base64_encode(openssl_x509_fingerprint($publicKey, "sha256", true));
        $certSerialNumber = $certData['serialNumber'];
        $certIssuer = $this->getIssuer($certData['issuer']);

        $SignedProperties = $this->generateSignedProperties($signTime, $certDigest, $certIssuer, $certSerialNumber, $SignedPropertiesId, $signPolicy);
        $SignedPropertiesWithSchemas = str_replace('<xades:SignedProperties', '<xades:SignedProperties ' . $this->get_schemas($doctype), $SignedProperties);
        $SignedPropertiesDigest = $this->base64Encode($SignedPropertiesWithSchemas);

        $KeyInfo = $this->getKeyInfo($KeyInfoId, $publicKey);
        $keyInfoWithShemas = str_replace('<ds:KeyInfo', '<ds:KeyInfo ' . $this->get_schemas($doctype), $KeyInfo);
        $kInfoDigest = $this->base64Encode($keyInfoWithShemas);

        $signedInfo = $this->getSignedInfo($documentDigest, $kInfoDigest, $SignedPropertiesDigest, $Reference0Id, $KeyInfoId, $SignedPropertiesId);
        $SignedInfoWithSchemas = str_replace('<ds:SignedInfo', '<ds:SignedInfo ' . $this->get_schemas($doctype), $signedInfo);

        $algo = "SHA256";
        openssl_sign($SignedInfoWithSchemas, $signatureResult, $privateKey, $algo);
        $signatureResult = base64_encode($signatureResult);


        $s = '<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Id="' . $signatureID . '">' . $signedInfo . '<ds:SignatureValue>' . $signatureResult . '</ds:SignatureValue>' . $KeyInfo . '<ds:Object><xades:QualifyingProperties Target="#' . $signatureID . '">' . $SignedProperties . '</xades:QualifyingProperties></ds:Object></ds:Signature>';

        // Buscar el nodo ExtensionContent vacío en lugar de usar reemplazo de string
        $d2 = new DOMDocument('1.0', 'UTF-8');
        $d2->loadXML($canonicalXML);
        $xpath = new DOMXPath($d2);
        $xpath->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');

        $extensionContentNodes = $xpath->query('//ext:UBLExtensions/ext:UBLExtension[last()]/ext:ExtensionContent');

        if ($extensionContentNodes->length > 0) {
            $extensionContentNode = $extensionContentNodes->item(0);

            // Si el nodo está vacío, insertar la firma
            if (!$extensionContentNode->hasChildNodes()) {
                $tempDoc = new DOMDocument('1.0', 'UTF-8');
                $tempDoc->loadXML($s);
                $signatureNode = $d2->importNode($tempDoc->documentElement, true);
                $extensionContentNode->appendChild($signatureNode);

                return $d2->saveXML();
            }
        }

        // Alternativa usando la versión anterior con reemplazo de string si el enfoque DOM no funciona
        $search = '<ext:ExtensionContent></ext:ExtensionContent>';
        $replace = '<ext:ExtensionContent>' . $s . "</ext:ExtensionContent>";
        $signed = str_replace($search, $replace, $canonicalXML);
        return $signed;
    }
}
