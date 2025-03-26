<?php

namespace App\Http\Controllers;

use SoapClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SoapController extends Controller
{
    public function enviarPeticion($nombreArchivoZIP, $zipBase64, $tipoAmbiente, $doctype=null){

        if($tipoAmbiente == 1) // Produccion
        {
            $wsdl = 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl';
            $location = 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc';
        }else{
            $wsdl = 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?wsdl';
            $location = 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc';
        }

        // Configurar el cliente SOAP
        $options = array(
            'trace' => 1, // Habilita el rastreo para depuración
            'exceptions' => true, // Lanza excepciones en caso de error
            'soap_version' => SOAP_1_2, // Especifica la versión del SOAP
            'location' => $location, // URL del servicio
            'uri' => 'http://wcf.dian.colombia', // Espacio de nombres del servicio
            'stream_context' => stream_context_create(array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false
                )
            ))
        );

        // Crear el cliente SOAP
        $response = @file_get_contents($wsdl);
        if ($response === FALSE) {
            // echo '<br>' .'No se puede acceder a la URL del WSDL.' . '<br>';
        } else {
            // echo '<br>' . 'Acceso al WSDL exitoso.' . '<br>';
        }
        try {
            $client = new SoapClient($wsdl, $options);
            // echo '<br>' . ' Cliente SOAP creado exitosamente' . '<br>';
        } catch (SoapFault $e) {
            // echo '<br>'. 'Error al crear el cliente SOAP: ' . $e->getMessage();
        }
        $stringSoap = $this->peticionSoap($nombreArchivoZIP, $zipBase64, $tipoAmbiente, $doctype);
        // echo '<br>' . 'Solicitud SOAP: ' . '<br>' . htmlentities($stringSoap) . '<br>';
        if ($tipoAmbiente == 1) {
            $soapEndpoint = 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc';
            $soapAction = 'http://wcf.dian.colombia/IWcfDianCustomerServices/SendBillSync';
        } else {
            $soapEndpoint = 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc';
            $soapAction = 'http://wcf.dian.colombia/IWcfDianCustomerServices/SendBillSync';
        }
        
        if ($doctype == 'nie') {
            if ($tipoAmbiente == 2) {
                $soapAction = 'http://wcf.dian.colombia/IWcfDianCustomerServices/SendTestSetAsync';
            } else {
                $soapAction = 'http://wcf.dian.colombia/IWcfDianCustomerServices/SendNominaSync';
            }
        }

        try {
            $response = $client->__doRequest($stringSoap, $options['location'], $soapAction, SOAP_1_2);
            $enviadoCorrectamente = true;
        } catch (SoapFault $e) {
            // Si ocurre un error en la llamada SOAP, lo capturamos
            //echo 'Error en el SOAP: ' . '<br>' . $e->getMessage();
            $enviadoCorrectamente = false; // Marcamos como no exitoso en caso de error
        }

        return $response;
    }

    function peticionSoap($nameZip, $zipBase64, $tipoAmbiente, $doctype = null) {

        // Obtener la fecha y hora actual con microsegundos
        $now = microtime(true);

        // Separar los segundos y los microsegundos
        $seconds = floor($now);
        $milliseconds = round(($now - $seconds) * 1000);

        // Formatear la fecha en el formato ISO 8601 con milisegundos
        $fechaCreated = gmdate('Y-m-d\TH:i:s', $seconds) . '.' . str_pad($milliseconds, 3, '0', STR_PAD_LEFT) . 'Z';

        // Obtener la fecha y hora de expiración (1 hora después)
        $expires = $now + 3600;
        $secondsExpires = floor($expires);
        $millisecondsExpires = round(($expires - $secondsExpires) * 1000);

        // Formatear la fecha de expiración en el formato ISO 8601 con milisegundos
        $fechaExpires = gmdate('Y-m-d\TH:i:s', $secondsExpires) . '.' . str_pad($millisecondsExpires, 3, '0', STR_PAD_LEFT) . 'Z';

        if($tipoAmbiente == 1){ // Produccion
            if ($doctype=='nie'){
                $string ='<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:wcf="http://wcf.dian.colombia">
                            <soap:Header xmlns:wsa="http://www.w3.org/2005/08/addressing"><wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"><wsu:Timestamp wsu:Id="TS-AD494D6A237F648F3A174257294356212"><wsu:Created>'. $fechaCreated .'</wsu:Created><wsu:Expires>'. $fechaExpires .'</wsu:Expires></wsu:Timestamp><wsse:BinarySecurityToken EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary" ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3" wsu:Id="X509-AD494D6A237F648F3A17425729435397">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</wsse:BinarySecurityToken><ds:Signature Id="SIG-AD494D6A237F648F3A174257294354511" xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"><ec:InclusiveNamespaces PrefixList="wsa soap wcf" xmlns:ec="http://www.w3.org/2001/10/xml-exc-c14n#"/></ds:CanonicalizationMethod><ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/><ds:Reference URI="#id-AD494D6A237F648F3A174257294354110"><ds:Transforms><ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"><ec:InclusiveNamespaces PrefixList="soap wcf" xmlns:ec="http://www.w3.org/2001/10/xml-exc-c14n#"/></ds:Transform></ds:Transforms><ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/><ds:DigestValue>BUMLxTBoVwBbsQdx0qLHbMj3oM7hiU+6RUBEVRgR98c=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>Y8Fua0KHt4yZCsCBpyZnpaTJSDOPs2ZW/5Xh4WtoB9OUbMY2HbtWy1BI8Dj5YMGa8Rfxa4wN+MRDuhApv4BlsFfogd6CSK1oiqepFsiZMbaAy/Ly1m/GsvPdKwaDGmx2zZq0kUsV3gmK8/H42QxV0xpUdtWkqUDkySfnP+kFRJjBocBRN/GhOGLuqPKWSavYmxO+00l/BWEClalSVmhJNrM+VocYYeVhDTUpV8eJRG4uEUcemLSaoQDJVFmxxhr8LlPaRDi12DGgGY8eIIJgwhVGuN7Lt3aJ4e8rS3R48JCC8TF8r6qVGvhl5VttCv0jeehGHvQpN8s7p6cTAYb3Vg==</ds:SignatureValue><ds:KeyInfo Id="KI-AD494D6A237F648F3A17425729435408"><wsse:SecurityTokenReference wsu:Id="STR-AD494D6A237F648F3A17425729435409"><wsse:Reference URI="#X509-AD494D6A237F648F3A17425729435397" ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3"/></wsse:SecurityTokenReference></ds:KeyInfo></ds:Signature></wsse:Security><wsa:Action>http://wcf.dian.colombia/IWcfDianCustomerServices/SendNominaSync</wsa:Action><wsa:To wsu:Id="id-AD494D6A237F648F3A174257294354110" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc</wsa:To></soap:Header>
                            <soap:Body>
                                <wcf:SendNominaSync>
                                    <!--Optional:-->
                                    <wcf:contentFile>' . $zipBase64 .'</wcf:contentFile>
                                </wcf:SendNominaSync>
                            </soap:Body>
                            </soap:Envelope>';
            } else {
                $string = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:wcf="http://wcf.dian.colombia">
                <soap:Header xmlns:wsa="http://www.w3.org/2005/08/addressing"><wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"><wsu:Timestamp wsu:Id="TS-79FCCDDB714B60C999174057261134012"><wsu:Created>' . $fechaCreated . '</wsu:Created><wsu:Expires>' . $fechaExpires . '</wsu:Expires></wsu:Timestamp><wsse:BinarySecurityToken EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary" ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3" wsu:Id="X509-79FCCDDB714B60C99917405726113237">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</wsse:BinarySecurityToken><ds:Signature Id="SIG-79FCCDDB714B60C999174057261132911" xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"><ec:InclusiveNamespaces PrefixList="wsa soap wcf" xmlns:ec="http://www.w3.org/2001/10/xml-exc-c14n#"/></ds:CanonicalizationMethod><ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/><ds:Reference URI="#id-79FCCDDB714B60C999174057261132610"><ds:Transforms><ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"><ec:InclusiveNamespaces PrefixList="soap wcf" xmlns:ec="http://www.w3.org/2001/10/xml-exc-c14n#"/></ds:Transform></ds:Transforms><ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/><ds:DigestValue>XZEG/K8JnSiywbjS2sJ3LZvs8UzJKK1J/BJ/y/AR3GQ=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>KyOBSjOpJHnplGEsHBLiYdjC4sMOOxQHGzaQW517YByEPW0+auPfXhs5WMovPiYZsHRpq+HdLK7gHjmYFmBeRc90LMyMbJUgDe/LIJhGYJAr1UxJB9Juxr/sri6YpxDtuDPJZWvchVtzYqyk0Fy0qwHr+TfjF9emOXslodfzCB09826nWmkA6J+UHDujES4KOPJfloYsWRvZfv4bwsgcyxaH2BTJZtpjJTazhvl8iH2eLKCSwUs5AsNlU5L7iywRzaQqIblcSTsPuIZptEp5IaXbZ0EzdK1TB3xNGIR8FW/B2KGlSMDS2d7thm2FMH9Qh7qYYcvrtJE8jxxc444XfQ==</ds:SignatureValue><ds:KeyInfo Id="KI-79FCCDDB714B60C99917405726113258"><wsse:SecurityTokenReference wsu:Id="STR-79FCCDDB714B60C99917405726113259"><wsse:Reference URI="#X509-79FCCDDB714B60C99917405726113237" ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3"/></wsse:SecurityTokenReference></ds:KeyInfo></ds:Signature></wsse:Security><wsa:Action>http://wcf.dian.colombia/IWcfDianCustomerServices/SendBillSync</wsa:Action><wsa:To wsu:Id="id-79FCCDDB714B60C999174057261132610" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">https://vpfe.dian.gov.co/WcfDianCustomerServices.svc</wsa:To></soap:Header>
                <soap:Body>
                    <wcf:SendBillSync>
                        <!--Optional:-->
                        <wcf:fileName>' . $nameZip . '</wcf:fileName>
                        <!--Optional:-->
                        <wcf:contentFile>' . $zipBase64 .'</wcf:contentFile>
                    </wcf:SendBillSync>
                </soap:Body>
                </soap:Envelope>';
            }

        } else {
            if ($doctype=='nie'){
                $string ='<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:wcf="http://wcf.dian.colombia">
                        <soap:Header xmlns:wsa="http://www.w3.org/2005/08/addressing"><wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"><wsu:Timestamp wsu:Id="TS-7564AC4BE05D93116C17419691462966"><wsu:Created>' . $fechaCreated . '</wsu:Created><wsu:Expires>' . $fechaExpires . '</wsu:Expires></wsu:Timestamp><wsse:BinarySecurityToken EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary" ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3" wsu:Id="X509-7564AC4BE05D93116C17419691462251">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</wsse:BinarySecurityToken><ds:Signature Id="SIG-7564AC4BE05D93116C17419691462515" xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"><ec:InclusiveNamespaces PrefixList="wsa soap wcf" xmlns:ec="http://www.w3.org/2001/10/xml-exc-c14n#"/></ds:CanonicalizationMethod><ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/><ds:Reference URI="#id-7564AC4BE05D93116C17419691462444"><ds:Transforms><ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"><ec:InclusiveNamespaces PrefixList="soap wcf" xmlns:ec="http://www.w3.org/2001/10/xml-exc-c14n#"/></ds:Transform></ds:Transforms><ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/><ds:DigestValue>t0CTeMqlecAQy7BtP8J0TSwFAovZuaMtwAjOEx/h2DU=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>Rz/ar+Amsf0XFNjRxut0ky/RzPIp5e+EJQeiQ2ZzNTwgJGqbeiYoyQWH+rqfQFuj6OJszjSefmG1&#13;
                        2elM9+tYYM4vI+Bb3vSOuuaX5gSAQg0LoKk+5x873r6zjPqzbBlq4B3Bvq9mNEIedIDAUVQlzAz6&#13;
                        +F0J+plMK0WbzJN/1KpYGgr5RRKsQQXo5z2PAAYvaz6LjiW/ptZb17qs3tji0zeMGx42qMLb9b7Q&#13;
                        rnD9FsgdBT3d8dp9cCmVgiudfWRqoFCpLLGeOTZm2xtlHUfRr3LFNDnnOtWavwhALsNGcyAxipoO&#13;
                        opgzTDF0Mk0voqaErOC4sZNVga3mtffZYO/Hew==</ds:SignatureValue><ds:KeyInfo Id="KI-7564AC4BE05D93116C17419691462412"><wsse:SecurityTokenReference wsu:Id="STR-7564AC4BE05D93116C17419691462423"><wsse:Reference URI="#X509-7564AC4BE05D93116C17419691462251" ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3"/></wsse:SecurityTokenReference></ds:KeyInfo></ds:Signature></wsse:Security><wsa:Action>http://wcf.dian.colombia/IWcfDianCustomerServices/SendTestSetAsync</wsa:Action><wsa:To wsu:Id="id-7564AC4BE05D93116C17419691462444" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc</wsa:To></soap:Header>
                        <soap:Body>
                            <wcf:SendTestSetAsync>
                                <!--Optional:-->
                                <wcf:fileName>'. $nameZip .'</wcf:fileName>
                                <!--Optional:-->
                                <wcf:contentFile>'. $zipBase64 .'</wcf:contentFile>
                                <!--Optional:-->
                                <wcf:testSetId>ce7c6423-312b-406c-a586-34442dd72ef2</wcf:testSetId>
                            </wcf:SendTestSetAsync>
                        </soap:Body>
                        </soap:Envelope>';
            } else {
                $string = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:wcf="http://wcf.dian.colombia">
                <soap:Header xmlns:wsa="http://www.w3.org/2005/08/addressing"><wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"><wsu:Timestamp wsu:Id="TS-242B5C3926B4922B37173651701326924"><wsu:Created>' . $fechaCreated . '</wsu:Created><wsu:Expires>' . $fechaExpires . '</wsu:Expires></wsu:Timestamp><wsse:BinarySecurityToken EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary" ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3" wsu:Id="X509-242B5C3926B4922B37173651701325919">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</wsse:BinarySecurityToken><ds:Signature Id="SIG-242B5C3926B4922B37173651701326023" xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"><ec:InclusiveNamespaces PrefixList="wsa soap wcf" xmlns:ec="http://www.w3.org/2001/10/xml-exc-c14n#"/></ds:CanonicalizationMethod><ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/><ds:Reference URI="#id-242B5C3926B4922B37173651701325922"><ds:Transforms><ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"><ec:InclusiveNamespaces PrefixList="soap wcf" xmlns:ec="http://www.w3.org/2001/10/xml-exc-c14n#"/></ds:Transform></ds:Transforms><ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/><ds:DigestValue>uCsLJXBgHmIZa6ccbX0UaQeLZg/os8nDxer6GmAGEN4=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>Zhimf7GUvGoawlLpJ0g1kHvJk4e3swqbQXvbAbHuoHDEA+1r0LPM8fYXJXh9hDYlHq2xtWGpTRgt+1fCyyKgHCP8J7Gr19X6YdWEWcyCl/LMXaN/dHe97RG7y5dMhqRowxiBcMLOe/GeW/Wd/xb9vY4QwbF2NLGIKKGJLYEBY2uRQETpqPqzthBsgBwWnhNkxA2CMIc36/o1PaN2A4kj8qw7PBUEny6FKFhNaKHDluD5eT0e92dIUWxMjnoCK6pM00UinsCbkx55/MD0V7wJtuvZ9Q9qrLj8k7S4HnP+ATn5isZ99UJVXENc9mkpqbWEuXQcRPAOc2JH1OrY16f3lw==</ds:SignatureValue><ds:KeyInfo Id="KI-242B5C3926B4922B37173651701325920"><wsse:SecurityTokenReference wsu:Id="STR-242B5C3926B4922B37173651701325921"><wsse:Reference URI="#X509-242B5C3926B4922B37173651701325919" ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3"/></wsse:SecurityTokenReference></ds:KeyInfo></ds:Signature></wsse:Security><wsa:Action>http://wcf.dian.colombia/IWcfDianCustomerServices/SendBillSync</wsa:Action><wsa:To wsu:Id="id-242B5C3926B4922B37173651701325922" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc</wsa:To></soap:Header>
                    <soap:Body>
                    <wcf:SendBillSync>
                        <!--Optional:-->
                        <wcf:fileName>' . $nameZip . '</wcf:fileName>
                        <!--Optional:-->
                        <wcf:contentFile>' . $zipBase64 .'</wcf:contentFile>
                    </wcf:SendBillSync>
                </soap:Body>
            </soap:Envelope>';
            }
        }

        return $string;

    }
    function enviarPeticionZipKey($zipKey) {
        $location = 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc';
        $wsdl = 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?wsdl';
        $options = array(
            'trace' => 1, // Habilita el rastreo para depuración
            'exceptions' => true, // Lanza excepciones en caso de error
            'soap_version' => SOAP_1_2, // Especifica la versión del SOAP
            'location' => $location, // URL del servicio
            'uri' => 'http://wcf.dian.colombia', // Espacio de nombres del servicio
            'stream_context' => stream_context_create(array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false
                )
            ))
        );
        // Obtener la fecha y hora actual con microsegundos
        $now = microtime(true);

        // Separar los segundos y los microsegundos
        $seconds = floor($now);
        $milliseconds = round(($now - $seconds) * 1000);

        // Formatear la fecha en el formato ISO 8601 con milisegundos
        $fechaCreated = gmdate('Y-m-d\TH:i:s', $seconds) . '.' . str_pad($milliseconds, 3, '0', STR_PAD_LEFT) . 'Z';

        // Obtener la fecha y hora de expiración (1 hora después)
        $expires = $now + 3600;
        $secondsExpires = floor($expires);
        $millisecondsExpires = round(($expires - $secondsExpires) * 1000);

        // Formatear la fecha de expiración en el formato ISO 8601 con milisegundos
        $fechaExpires = gmdate('Y-m-d\TH:i:s', $secondsExpires) . '.' . str_pad($millisecondsExpires, 3, '0', STR_PAD_LEFT) . 'Z';

        $string = '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:wcf="http://wcf.dian.colombia">
                    <soap:Header xmlns:wsa="http://www.w3.org/2005/08/addressing"><wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"><wsu:Timestamp wsu:Id="TS-7564AC4BE05D93116C174196982295312"><wsu:Created>' . $fechaCreated . '</wsu:Created><wsu:Expires>' . $fechaExpires . '</wsu:Expires></wsu:Timestamp><wsse:BinarySecurityToken EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary" ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3" wsu:Id="X509-7564AC4BE05D93116C17419698229317">MIIHOjCCBSKgAwIBAgIKOR7b+6g9rtdPIDANBgkqhkiG9w0BAQsFADCBhjEeMBwGCSqGSIb3DQEJARYPaW5mb0Bnc2UuY29tLmNvMSUwIwYDVQQDExxBdXRvcmlkYWQgU3Vib3JkaW5hZGEgMDEgR1NFMQwwCgYDVQQLEwNQS0kxDDAKBgNVBAoTA0dTRTEUMBIGA1UEBxMLQm9nb3RhIEQuQy4xCzAJBgNVBAYTAkNPMB4XDTI0MTIxMzIzMTUzM1oXDTI1MTIxMzIzMjUzMVowggEHMTAwLgYDVQQJDCdDQUxMRSAzMCBOIDI4LTY5IFBJU08gMyAtIEJyciBlbCBjZW50cm8xIzAhBgNVBA0MGkZFUEogR1NFIENMIDc3IDcgNDQgT0YgNzAxMREwDwYDVQQIDAhDQVNBTkFSRTEOMAwGA1UEBwwFWU9QQUwxCzAJBgNVBAYTAkNPMS4wLAYDVQQDDCVTSVNPRlQgU09MVUNJT05FUyBJTkZPUk1BVElDQVMgUy5BLlMuMRkwFwYKKwYBBAGkZgEDAgwJOTAwMzY0MDMyMQwwCgYDVQQpDANOSVQxEjAQBgNVBAUTCTkwMDM2NDAzMjERMA8GA1UECwwIR0VSRU5DSUEwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCY2Ic9KI704oRrMjbOpEMr8rex/f2vfN7vO/8Pfx3uIqaQPl8qc8Z46diSlgsrTbVFVGo0HvRAWcigeCjquUsdsGEQONIe6og75KR943pHeN1nDe0qscjc+WkyexkN1SQPKdteC5H5ft3JXmcSwXmMl/W5zTItV+slKrlL/iNOV/Ve7fB3W9iKKOg24i8srUrxWtRqJaN977sv8DdoYLllbM54vyer4sxTqzzHxLBX3z4ei3r6QR2lhrm3CLYCyIW0OuskCbtL8i79vQ/LJYXODKsE+aQepTOAnZyrCm8uCZwAyTO+e3wyHtPvw+/iUWUeK0/2v0U+k1/5hqm9+GLpAgMBAAGjggIkMIICIDAMBgNVHRMBAf8EAjAAMB8GA1UdIwQYMBaAFEG81Dl4uIOjFxoImqm4BAIJLdiZMGgGCCsGAQUFBwEBBFwwWjAyBggrBgEFBQcwAoYmaHR0cHM6Ly9jZXJ0czIuZ3NlLmNvbS5jby9DQV9TVUIwMS5jcnQwJAYIKwYBBQUHMAGGGGh0dHBzOi8vb2NzcDIuZ3NlLmNvbS5jbzBwBgNVHREEaTBngRpjb250YWJpbGlkYWRAc2lzb2Z0LmNvbS5jb4ZJaHR0cHM6Ly9nc2UuY29tLmNvL2RvY3VtZW50b3MvY2VydGlmaWNhY2lvbmVzL2FjcmVkaXRhY2lvbi8xNi1FQ0QtMDAxLnBkZjCBgwYDVR0gBHwwejB4BgsrBgEEAYHzIAEEEDBpMGcGCCsGAQUFBwIBFltodHRwczovL2dzZS5jb20uY28vZG9jdW1lbnRvcy9jYWxpZGFkL0RQQy9EZWNsYXJhY2lvbl9kZV9QcmFjdGljYXNfZGVfQ2VydGlmaWNhY2lvbl9WMTgucGRmMCcGA1UdJQQgMB4GCCsGAQUFBwMCBggrBgEFBQcDBAYIKwYBBQUHAwEwNQYDVR0fBC4wLDAqoCigJoYkaHR0cHM6Ly9jcmwyLmdzZS5jb20uY28vQ0FfU1VCMDEuY3JsMB0GA1UdDgQWBBSWW7IlhhuJ+DUddOhp3iCdBC/xPzAOBgNVHQ8BAf8EBAMCBPAwDQYJKoZIhvcNAQELBQADggIBAEJObHi9fp0aROhc665b2ovCX86as4t8ZUOcEqSEL8NukjHEHE4RCV4vsP+CbBG7iubhiqVJelR5SfMJKGPF65T9VGBwjENxbEOybzlQpzvezgI0vOYrCcE8mm6z7SWg7SEk8jQEyp7u2DMN1Z1VMDfbFl1muVL1LU8cnwhfSCgmLZ+YEgNM3Jbg+iulXL6YBFjZ5FfB4psNhJ2tgt1ErJxSme4mOn9krNRJPinwer2ZJjqlR4MafUjzud5zXalXVC01wvlCDTGRkdukaQNaOSyhDl1INP0hUBSp7SJZs5bw/ivDTgPVeHq/8UtKbGrqjIr+bjY46WpWPxgr60MQRub4IEcq/putSSBlIqwS8+yEDS3g6/kyAtHa0oYT2bbhBMv53ERZOVucJWrLyaxpxctVtCvd1fv0FjvHV3r1Y+BEoAXPYUW9ACoxLwNUNcl2x7MViuzdWLW1+70TPid6NsSJp/LeltrhhkmdUp15CBj1y07Q4yCAbAstLKK8feS1VRvM3E24xqpTOJmWUCx11STo6U0DBlmVTj4qm4F0fvZruTN5tdZmyhRtjqRIbyyqt8bskUTuBFWS5/Zmy+Wfl9hKZLlKdwpmi03pvgdjGIVFj79Fxl12TaS4ub+uGjdG/ueMSD21Ba0K5CSK5/ofI7ECe/0T5a6l1aL6JpT6JvQc</wsse:BinarySecurityToken><ds:Signature Id="SIG-7564AC4BE05D93116C174196982293911" xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"><ec:InclusiveNamespaces PrefixList="wsa soap wcf" xmlns:ec="http://www.w3.org/2001/10/xml-exc-c14n#"/></ds:CanonicalizationMethod><ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/><ds:Reference URI="#id-7564AC4BE05D93116C174196982293410"><ds:Transforms><ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"><ec:InclusiveNamespaces PrefixList="soap wcf" xmlns:ec="http://www.w3.org/2001/10/xml-exc-c14n#"/></ds:Transform></ds:Transforms><ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/><ds:DigestValue>aVOV84L3ZHgd3restRobtWUXHnhZreLPC5kjB8vDKug=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>eCqRMuLTcZgSRoi5wkyyPgtQIKcevV5NZOSpVLnEq7eAaBg+YhS8a1kffX5I+9aae1fq+hdv2fT9R3TMpaZRGrmGC0FQpdkJK3uwV26egcvavNrGwWkqu34h+OBOU1K9F/isR8pd544LoF+G6eYgk5LWodT2al/qOdERYeLdTmX8moQz7AoCzyAQyge67r8vyIaINwpT1ILaErUNbm0Ujm2c0o9jWTJiQKB1hHK9ukDuk0uQm1GolQ5qSdzycvGS3jADZCJSAOceBxQiomLDzfD8dmFWJvZLT7BilBq1IMBQVPJVhYDYLxjwsaZ9yCkyPrdWwDwuAMf4dzFi+u2Wew==</ds:SignatureValue><ds:KeyInfo Id="KI-7564AC4BE05D93116C17419698229338"><wsse:SecurityTokenReference wsu:Id="STR-7564AC4BE05D93116C17419698229339"><wsse:Reference URI="#X509-7564AC4BE05D93116C17419698229317" ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3"/></wsse:SecurityTokenReference></ds:KeyInfo></ds:Signature></wsse:Security><wsa:Action>http://wcf.dian.colombia/IWcfDianCustomerServices/GetStatusZip</wsa:Action><wsa:To wsu:Id="id-7564AC4BE05D93116C174196982293410" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc</wsa:To></soap:Header>
                    <soap:Body>
                        <wcf:GetStatusZip>
                            <!--Optional:-->
                            <wcf:trackId>'.$zipKey.'</wcf:trackId>
                        </wcf:GetStatusZip>
                    </soap:Body>
                    </soap:Envelope>';

        $soapEndpoint = 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc';
        $soapAction = 'http://wcf.dian.colombia/IWcfDianCustomerServices/GetStatusZip';
        try {
            $client = new SoapClient($wsdl, $options);
            // echo '<br>' . ' Cliente SOAP creado exitosamente' . '<br>';
        } catch (SoapFault $e) {
            // echo '<br>'. 'Error al crear el cliente SOAP: ' . $e->getMessage();
        }
        try {
            $response = $client->__doRequest($string, $options['location'], $soapAction, SOAP_1_2);
            $enviadoCorrectamente = true;
        } catch (SoapFault $e) {
            // Si ocurre un error en la llamada SOAP, lo capturamos
            //echo 'Error en el SOAP: ' . '<br>' . $e->getMessage();
            $enviadoCorrectamente = false; // Marcamos como no exitoso en caso de error
        }

        return $response;
    }
}
