<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class XmlController extends Controller
{
    public function crearXML(Request $request){

        $xmlSinFirmar = '';

        $request = json_decode(json_encode($request->all()));
        // log::info("Request: " . json_encode($request));
        $tipoAmbiente = $request->tipoAmbiente; // 2 = Prueba | 1 = Produccion

        // Datos Dian
        $nitDian = '800197268';

        // Datos Provedor Tecnologico (Sisoft)
        if($tipoAmbiente == 1){ // Produccion
            $prefix = 'PB';
            $from = '1';
            $to = '500';
            $numResolucion = '18764089574197';
            $startDate = '2025-02-27';
            $endDate = '2027-02-27';
            $claveTecnica = '47b8a8ff8f50e138336f866802049748dac8fbcfcec1344eed615cebb50882dc';
        }else{ // Prueba
            $prefix = 'SETP';
            $from = '990000000';
            $to = '995000000';
            $numResolucion = '18760000001';
            $startDate = '2019-01-19';
            $endDate = '2030-01-19';
            $claveTecnica = 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c';
        }
        $SoftwarePin = '12345';
        $nitSisoft = '900364032';
        $dvSisoft = $this->calcularDV($nitSisoft);
        $tipoIdentificadorFiscalSisoft = '31'; // 31 = NIT
        //$idSoftware = '6e2162cb-1da5-45a5-bb85-fa4ebc020f32';
        $idSoftware = '51cb05b8-2031-468f-b2d8-fdcafd34e44b';
        // $claveTecnica = 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c';

        // Datos Líneas
        $productos = [];

        foreach ($request->productos as $producto) {
            $productos[] = [
                'numeroLinea' => $producto->numero_linea,
                'idItemSeller' => $producto->id_seller,
                'itemDescripcion' => $producto->descripcion,
                'cantidadProducto' => $producto->cantidad,
                'codigoUnidadCantidad' => $producto->codigo_unidad,
                'codigoUnidadCantidadItem' => $producto->codigo_unidad_item,
                'valorUnitario' => $producto->valor_unitario, // Estaba sobrescribiendo 'codigoUnidadCantidadItem'
                'valorTotal' => $producto->valor_total,
                'declaraIva' => $producto->declara_iva,
                'valorBruto' => $producto->valor_bruto,
            ];
        }

        $otrosImpuestos = [];

        foreach ($request->otros_impuestos as $impuesto) {
            $otrosImpuestos[] = [
                'id' => $impuesto->id,
                'nombre' => $impuesto->nombre,
                'porcentaje' => $impuesto->porcentaje,
                'valorImpuesto' => $impuesto->valor_impuesto,
                'valorBruto' => $impuesto->valor_bruto,
            ];
        }

        // Datos Emision
        $FechaEmision = date('Y-m-d');
        $HoraEmision = date('H:i:s') . '-05:00';
        $preFactura = $from + $request->factura;
        $facturaId = $prefix . $preFactura;
        $huellaSisoft = $this->generarHuellaSoftware($facturaId, $SoftwarePin, $idSoftware);
        $docCliente = $request->cliente->documento;

        // Datos Factura
        $valorTotalFactura = $this->formatoMonedaDian($request->valor_total_factura);
        $valorSinImpuestosFactura = $this->formatoMonedaDian($request->valor_sin_impuestos_factura);
        $valorImpuestosFactura = $this->formatoMonedaDian($request->valor_impuestos_factura);
        // $valorIvaFactura = $this->formatoMonedaDian($this->ivasProductos($productos));
        $valorIvaFactura = $this->formatoMonedaDian($request->valor_iva_factura);
        $valorOtroImpuestosFactura = $this->formatoMonedaDian($request->valor_otro_impuestos_factura + $request->valor_impuesto_consumo + $request->valor_impuesto_ica);
        $valorConsumo = $this->formatoMonedaDian($request->valor_impuesto_consumo);
        $valorIca = $this->formatoMonedaDian($request->valor_impuesto_ica);

        $CUFE = $this->generarCUFE($facturaId, $FechaEmision, $HoraEmision, $valorTotalFactura, $valorSinImpuestosFactura, $valorIvaFactura, $valorConsumo, $valorIca ,$nitSisoft, $docCliente, $tipoAmbiente, $claveTecnica);
        $qrCode = $this->generarQR($facturaId, $FechaEmision, $HoraEmision, $nitSisoft, $docCliente, $valorSinImpuestosFactura, $valorIvaFactura, $valorOtroImpuestosFactura, $valorTotalFactura, $CUFE, $tipoAmbiente);

        // Datos Version Factura
        $tipoOperacion = '10'; // 10 = Estandar | Tabla 13.1.5.1
        $tipoFactura = '01'; // 01 = Factura de Venta | Tabla 13.1.3
        $descripcionFac = $request->descripcion_factura;
        $numeroItemsFac = $request->numero_items_factura; // Tener en cuenta el Foreach para los items de la factura

        // Datos Supplier (Emisor)
        $tipoOrganizacionSupplier = $request->proveedor->tipo_organizacion; // 1 = Persona Juridica | 2 = Persona Natural
        $nombreComercialSupplier = $request->proveedor->nombre_comercial;
        $nombreSupplierFiscal = $request->proveedor->nombre_fiscal;
        $nitSupplier = $request->proveedor->nit;
        $direccionSupplierFiscal = $request->proveedor->direccion_fiscal;
        $direccionComercialSupplier = $request->proveedor->direccion_comercial;
        $codMunicipioSupplierFiscal = $request->proveedor->ubicacion_fiscal->codigo_municipio;
        $nombreMunicipioSupplierFiscal = $request->proveedor->ubicacion_fiscal->nombre_municipio;
        $codDptoSupplierFiscal = $request->proveedor->ubicacion_fiscal->codigo_departamento;
        $nombreDptoSupplierFiscal = $request->proveedor->ubicacion_fiscal->nombre_departamento;
        $codMunicipioComercialSupplier = $request->proveedor->ubicacion_comercial->codigo_municipio;
        $nombreMunicipioComercialSupplier = $request->proveedor->ubicacion_comercial->nombre_municipio;
        $codDptoComercialSupplier = $request->proveedor->ubicacion_comercial->codigo_departamento;
        $nombreDptoComercialSupplier = $request->proveedor->ubicacion_comercial->nombre_departamento;

        $dvSupplier = $this->calcularDV($nitSupplier);
        $tipoIdentificadorFiscalSupplier = $request->proveedor->tipo_identificador_fiscal; // 31 = NIT | 13 = Cedula
        $RegimenSupplier = $request->proveedor->regimen; // No sabemos por que es 04 pero es eso
        $responsabilidadFiscalSupplier = $request->proveedor->responsabilidad_fiscal; // R-99-PN = No Responsable | Tabla 13.2.6.1

        $idTributoSupplier = $request->proveedor->tributo->id; // 01 = IVA | 04 = Impuesto al Consumo (INC) | ZA = IVA y INC | ZZ = No aplica | Codigo de Tabla 13.2.6.2
        $nombreTributoSupplier = $request->proveedor->tributo->nombre; // 01 = IVA | ZZ = No aplica | Nombre de Tabla 13.2.6.2

        $contactNameSupplier = $request->proveedor->contacto->nombre;
        $contactPhoneSupplier = $request->proveedor->contacto->telefono;
        $contactEmailSupplier = $request->proveedor->contacto->email;
        $contactNoteSupplier = $request->proveedor->contacto->nota;

        // Datos Cliente (Customer)
        $tipoOrganizacionCustomer = $request->cliente->tipo_organizacion; // 1 = Persona Juridica | 2 = Persona Natural
        $docCliente = $request->cliente->documento;
        $nombreCliente = $request->cliente->nombre;
        $correCliente = $request->cliente->correo;
        $telCliente = $request->cliente->telefono;
        $direccionComercialCustomer = $request->cliente->direccion_comercial;

        $codMunicipioComercialCustomer = $request->cliente->ubicacion_comercial->codigo_municipio;
        $nombreMunicipioComercialCustomer = $request->cliente->ubicacion_comercial->nombre_municipio;
        $codDptoComercialCustomer = $request->cliente->ubicacion_comercial->codigo_departamento;
        $nombreDptoComercialCustomer = $request->cliente->ubicacion_comercial->nombre_departamento;

        $tipoIdentificadorFiscalCustomer = $request->cliente->identificador_fiscal->tipo; // 31 = NIT | 13 = Cedula
        $idTributoCustomerFiscal = $request->cliente->identificador_fiscal->tributo->id; // 01 = IVA | 04 = Impuesto al Consumo (INC) | ZA = IVA y INC | ZZ = No aplica | Codigo de Tabla
        $nombreTributoCustomer = $request->cliente->identificador_fiscal->tributo->nombre; // 01 = IVA | ZZ = No aplica | Nombre de Tabla

        $nombreComercialCustomer = $nombreCliente;
        $nombreCustomerFiscal = $nombreCliente;
        $dvCustomer = $this->calcularDV($docCliente);
        $nitCustomer = $docCliente;

        // Datos Totales
        $formaPago = $request->pago->forma; // 1 = Contado | 2 = Credito | 3 = Otro | Tabla 13.3.4.1
        $medioPago = $request->pago->medio; // 10 = Efectivo | 41 = Tarjeta de Credito | 42 = Tarjeta Debito | Tabla 13.3.4.2
        $fechaVencimientoFactura = $request->pago->fecha_vencimiento;

        $totalValorBruto = $valorSinImpuestosFactura;
        $totalBaseTributo = $this->formatoMonedaDian($valorSinImpuestosFactura + $request->valor_adicional_declarar);
        $totalValorBrutoMasTributo = $this->formatoMonedaDian($request->valor_total_factura);

        // $numeroLinea = $request->productos->numero_linea;
        // $cantidadProducto = $request->cantidad_producto;
        // $codigoUnidadCantidad = $request->codigo_unidad_cantidad;
        // $itemDescripcion = $request->descripcion_producto;
        // $idItemSeller = $request->id_item_seller;
        // $codigoUnidadCantidadItem = $request->codigo_unidad_cantidad_item;
        // $cantidadItem = $request->cantidad_item;
        // $valorTotalLinea = $valorTotalFactura;

        // LLAMADO DE FUNCIONES PARA CONSTRUIR EL .XML
        $headXML = $this->formHeadXML();
        $extensionXML = $this->formExtensionXML($numResolucion, $startDate, $endDate, $prefix, $from, $to, $nitSisoft, $dvSisoft, $tipoIdentificadorFiscalSisoft, $idSoftware, $huellaSisoft, $nitDian, $qrCode);
        $firmaXML = $this->formSignatureXML();
        $versionXML = $this->formVersionXML($tipoOperacion, $tipoAmbiente, $facturaId, $CUFE, $FechaEmision, $HoraEmision, $tipoFactura, $descripcionFac, $numeroItemsFac);
        $supplierXML = $this->formSupplierXML($tipoOrganizacionSupplier, $nombreComercialSupplier, $codMunicipioComercialSupplier, $nombreMunicipioComercialSupplier, $nombreDptoComercialSupplier, $codDptoComercialSupplier, $direccionComercialSupplier, $nitSupplier, $dvSupplier, $tipoIdentificadorFiscalSupplier, $RegimenSupplier, $responsabilidadFiscalSupplier, $nombreSupplierFiscal, $nombreMunicipioSupplierFiscal, $nombreDptoSupplierFiscal, $codMunicipioSupplierFiscal, $codDptoSupplierFiscal, $direccionSupplierFiscal, $idTributoSupplier, $nombreTributoSupplier, $prefix, $contactNameSupplier, $contactPhoneSupplier, $contactEmailSupplier, $contactNoteSupplier);
        $customerXML = $this->formCustomerXML($tipoOrganizacionCustomer, $nombreComercialCustomer, $codMunicipioComercialCustomer, $nombreMunicipioComercialCustomer,
                        $nombreDptoComercialCustomer, $codDptoComercialCustomer, $direccionComercialCustomer, $nombreCustomerFiscal, $dvCustomer,
                        $tipoIdentificadorFiscalCustomer, $nitCustomer, $idTributoCustomerFiscal, $nombreTributoCustomer);
        $totalsXML = $this->formTotalsXML($formaPago, $medioPago, $totalValorBruto, $totalBaseTributo, $totalValorBrutoMasTributo, $valorTotalFactura, $productos, $otrosImpuestos, $fechaVencimientoFactura);
        // $lineXML = $this->formLinesXML($numeroLinea, $cantidadProducto, $codigoUnidadCantidad, $valorTotalLinea, $itemDescripcion, $idItemSeller, $codigoUnidadCantidadItem, $cantidadItem);
        $lineXML = $this->formLinesXML($productos, $otrosImpuestos);

        $xmlSinFirmar = $headXML . $extensionXML . $firmaXML . $versionXML . $supplierXML . $customerXML . $totalsXML . $lineXML . '</Invoice>';
        if($tipoAmbiente == 1){ // Producción
            $url = "https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey=$CUFE";
        } else{
            $url = "https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey=$CUFE";
        }
        $response['xmlSinFirmar'] = $xmlSinFirmar;
        $response['fechaEmision'] = $FechaEmision;
        $response['horaEmision'] = $HoraEmision;
        $response['factura_id'] = $facturaId;
        $response['sequence'] = $preFactura;
        $response['cufe'] = $CUFE;
        $response['qrlink'] = $url;

        return $response;
    }

    public function formHeadXML(){
        $string = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
        xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
        xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
        xmlns:ds="http://www.w3.org/2000/09/xmldsig#"
        xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"
        xmlns:sts="dian:gov:co:facturaelectronica:Structures-2-1"
        xmlns:xades="http://uri.etsi.org/01903/v1.3.2#"
        xmlns:xades141="http://uri.etsi.org/01903/v1.4.1#"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2     http://docs.oasis-open.org/ubl/os-UBL-2.1/xsd/maindoc/UBL-Invoice-2.1.xsd">';

        return $string;
    }

    public function formExtensionXML($numResolucion, $startDate, $endDate, $prefix, $from, $to, $nitSisoft, $dvSisoft, $tipoIdentificadorFiscalSisoft, $idSoftware, $huellaSisoft, $nitDian, $qrTexto){
        $string = "<ext:UBLExtensions>
            <ext:UBLExtension>
                <ext:ExtensionContent>
                    <sts:DianExtensions>
                    <sts:InvoiceControl>
                        <sts:InvoiceAuthorization>$numResolucion</sts:InvoiceAuthorization>
                        <sts:AuthorizationPeriod>
                            <cbc:StartDate>$startDate</cbc:StartDate>
                            <cbc:EndDate>$endDate</cbc:EndDate>
                        </sts:AuthorizationPeriod>
                        <sts:AuthorizedInvoices>
                            <sts:Prefix>$prefix</sts:Prefix>
                            <sts:From>$from</sts:From>
                            <sts:To>$to</sts:To>
                        </sts:AuthorizedInvoices>
                    </sts:InvoiceControl>
                    <sts:InvoiceSource>
                        <cbc:IdentificationCode listAgencyID='6' listAgencyName='United Nations Economic Commission for Europe' listSchemeURI='urn:oasis:names:specification:ubl:codelist:gc:CountryIdentificationCode-2.1'>CO</cbc:IdentificationCode>
                    </sts:InvoiceSource>
                    <sts:SoftwareProvider>
                        <sts:ProviderID schemeAgencyID='195' schemeAgencyName='CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)' schemeID='$dvSisoft' schemeName='$tipoIdentificadorFiscalSisoft'>$nitSisoft</sts:ProviderID>
                        <sts:SoftwareID schemeAgencyID='195' schemeAgencyName='CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)'>$idSoftware</sts:SoftwareID>
                    </sts:SoftwareProvider>
                    <sts:SoftwareSecurityCode schemeAgencyID='195' schemeAgencyName='CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)'>$huellaSisoft</sts:SoftwareSecurityCode>
                    <sts:AuthorizationProvider>
                        <sts:AuthorizationProviderID schemeAgencyID='195' schemeAgencyName='CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)' schemeID='4' schemeName='31'>$nitDian</sts:AuthorizationProviderID>
                    </sts:AuthorizationProvider>
                    <sts:QRCode>$qrTexto</sts:QRCode>
                    </sts:DianExtensions>
                </ext:ExtensionContent>
            </ext:UBLExtension>";

        return $string;
    }

    public function formSignatureXML(){
        $string = "<ext:UBLExtension>
                    <ext:ExtensionContent></ext:ExtensionContent>
                </ext:UBLExtension>
            </ext:UBLExtensions>";

        return $string;
    }

    public function formVersionXML($tipoOperacion, $tipoAmbiente, $facturaId, $CUFE, $fechaFactura, $horaFactura, $tipoFactura, $descripcionFac, $numeroItemsFac){
        $string = "<cbc:UBLVersionID>UBL 2.1</cbc:UBLVersionID>
            <cbc:CustomizationID>$tipoOperacion</cbc:CustomizationID>
            <cbc:ProfileID>DIAN 2.1: Factura Electrónica de Venta</cbc:ProfileID>
            <cbc:ProfileExecutionID>$tipoAmbiente</cbc:ProfileExecutionID>
            <cbc:ID>$facturaId</cbc:ID>
            <cbc:UUID schemeID='$tipoAmbiente' schemeName='CUFE-SHA384'>$CUFE</cbc:UUID>
            <cbc:IssueDate>$fechaFactura</cbc:IssueDate>
            <cbc:IssueTime>$horaFactura</cbc:IssueTime>
            <cbc:InvoiceTypeCode>$tipoFactura</cbc:InvoiceTypeCode>
            <cbc:Note>$descripcionFac</cbc:Note>
            <cbc:DocumentCurrencyCode listAgencyID='6' listAgencyName='United Nations Economic Commission for Europe' listID='ISO 4217 Alpha'>COP</cbc:DocumentCurrencyCode>
            <cbc:LineCountNumeric>$numeroItemsFac</cbc:LineCountNumeric>";

        return $string;
    }

    public function formSupplierXML($tipoOrganizacionSupplier, $nombreComercialSupplier, $codMunicipioComercialSupplier, $nombreMunicipioComercialSupplier, $nombreDptoComercialSupplier, $codDptoComercialSupplier, $direccionComercialSupplier, $nitSupplier, $dvSupplier, $tipoIdentificadorFiscalSupplier, $RegimenSupplier, $responsabilidadFiscalSupplier, $nombreSupplierFiscal, $nombreMunicipioSupplierFiscal, $nombreDptoSupplierFiscal, $codMunicipioSupplierFiscal, $codDptoSupplierFiscal, $direccionSupplierFiscal, $idTributoSupplier, $nombreTributoSupplier, $prefix, $contactNameSupplier, $contactPhoneSupplier, $contactEmailSupplier, $contactNoteSupplier){

        $string = "<cac:AccountingSupplierParty>
      <cbc:AdditionalAccountID>$tipoOrganizacionSupplier</cbc:AdditionalAccountID>
      <cac:Party>
         <cac:PartyName>
            <cbc:Name>$nombreComercialSupplier</cbc:Name>
         </cac:PartyName>
         <cac:PhysicalLocation>
            <cac:Address>
               <cbc:ID>$codMunicipioComercialSupplier</cbc:ID>
               <cbc:CityName>$nombreMunicipioComercialSupplier</cbc:CityName>
               <cbc:CountrySubentity>$nombreDptoComercialSupplier</cbc:CountrySubentity>
               <cbc:CountrySubentityCode>$codDptoComercialSupplier</cbc:CountrySubentityCode>
               <cac:AddressLine>
                  <cbc:Line>$direccionComercialSupplier</cbc:Line>
               </cac:AddressLine>
               <cac:Country>
                  <cbc:IdentificationCode>CO</cbc:IdentificationCode>
                  <cbc:Name languageID='es'>Colombia</cbc:Name>
               </cac:Country>
            </cac:Address>
         </cac:PhysicalLocation>
         <cac:PartyTaxScheme>
            <cbc:RegistrationName>$nombreSupplierFiscal</cbc:RegistrationName>
            <cbc:CompanyID schemeAgencyID='195' schemeAgencyName='CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)' schemeID='$dvSupplier' schemeName='$tipoIdentificadorFiscalSupplier'>$nitSupplier</cbc:CompanyID>
            <cbc:TaxLevelCode listName='$RegimenSupplier'>$responsabilidadFiscalSupplier</cbc:TaxLevelCode>
            <cac:RegistrationAddress>
               <cbc:ID>$codMunicipioSupplierFiscal</cbc:ID>
               <cbc:CityName>$nombreMunicipioSupplierFiscal</cbc:CityName>
               <cbc:CountrySubentity>$nombreDptoSupplierFiscal</cbc:CountrySubentity>
               <cbc:CountrySubentityCode>$codDptoSupplierFiscal</cbc:CountrySubentityCode>
               <cac:AddressLine>
                  <cbc:Line>$direccionSupplierFiscal</cbc:Line>
               </cac:AddressLine>
               <cac:Country>
                  <cbc:IdentificationCode>CO</cbc:IdentificationCode>
                  <cbc:Name languageID='es'>Colombia</cbc:Name>
               </cac:Country>
            </cac:RegistrationAddress>
            <cac:TaxScheme>
               <cbc:ID>$idTributoSupplier</cbc:ID>
               <cbc:Name>$nombreTributoSupplier</cbc:Name>
            </cac:TaxScheme>
         </cac:PartyTaxScheme>
         <cac:PartyLegalEntity>
            <cbc:RegistrationName>$nombreSupplierFiscal</cbc:RegistrationName>
            <cbc:CompanyID schemeAgencyID='195' schemeAgencyName='CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)' schemeID='$dvSupplier' schemeName='$tipoIdentificadorFiscalSupplier'>$nitSupplier</cbc:CompanyID>
            <cac:CorporateRegistrationScheme>
               <cbc:ID>$prefix</cbc:ID>
            </cac:CorporateRegistrationScheme>
         </cac:PartyLegalEntity>
         <cac:Contact>
            <cbc:Name>$contactNameSupplier</cbc:Name>
            <cbc:Telephone>$contactPhoneSupplier</cbc:Telephone>
            <cbc:ElectronicMail>$contactEmailSupplier</cbc:ElectronicMail>
            <cbc:Note>$contactNoteSupplier</cbc:Note>
         </cac:Contact>
      </cac:Party>
   </cac:AccountingSupplierParty>";

        return $string;
    }
    // Para persona Natural
    public function formCustomerXML($tipoOrganizacionCustomer, $nombreComercialCustomer, $codMunicipioComercialCustomer, $nombreMunicipioComercialCustomer,
                                    $nombreDptoComercialCustomer, $codDptoComercialCustomer, $direccionComercialCustomer, $nombreCustomerFiscal, $dvCustomer,
                                    $tipoIdentificadorFiscalCustomer, $nitCustomer, $idTributoCustomerFiscal, $nombreTributoCustomer){

        $string = "<cac:AccountingCustomerParty>
      <cbc:AdditionalAccountID>$tipoOrganizacionCustomer</cbc:AdditionalAccountID>
      <cac:Party>
        <cac:PartyIdentification>
            <cbc:ID schemeAgencyID='195' schemeAgencyName='CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)' schemeID='$dvCustomer' schemeName='$tipoIdentificadorFiscalCustomer'>$nitCustomer</cbc:ID>
        </cac:PartyIdentification>
         <cac:PartyName>
            <cbc:Name>$nombreComercialCustomer</cbc:Name>
         </cac:PartyName>
         <cac:PhysicalLocation>
            <cac:Address>
               <cbc:ID>$codMunicipioComercialCustomer</cbc:ID>
               <cbc:CityName>$nombreMunicipioComercialCustomer</cbc:CityName>
               <cbc:CountrySubentity>$nombreDptoComercialCustomer</cbc:CountrySubentity>
               <cbc:CountrySubentityCode>$codDptoComercialCustomer</cbc:CountrySubentityCode>
               <cac:AddressLine>
                  <cbc:Line>$direccionComercialCustomer</cbc:Line>
               </cac:AddressLine>
               <cac:Country>
                  <cbc:IdentificationCode>CO</cbc:IdentificationCode>
                  <cbc:Name languageID='es'>Colombia</cbc:Name>
               </cac:Country>
            </cac:Address>
         </cac:PhysicalLocation>
         <cac:PartyTaxScheme>
            <cbc:RegistrationName>$nombreCustomerFiscal</cbc:RegistrationName>
            <cbc:CompanyID schemeAgencyID='195' schemeAgencyName='CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)' schemeID='$dvCustomer' schemeName='$tipoIdentificadorFiscalCustomer'>$nitCustomer</cbc:CompanyID>
            <cac:TaxScheme>
               <cbc:ID>$idTributoCustomerFiscal</cbc:ID>
               <cbc:Name>$nombreTributoCustomer</cbc:Name>
            </cac:TaxScheme>
         </cac:PartyTaxScheme>
         <cac:PartyLegalEntity>
            <cbc:RegistrationName>$nombreCustomerFiscal</cbc:RegistrationName>
            <cbc:CompanyID schemeAgencyID='195' schemeAgencyName='CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)' schemeID='$dvCustomer' schemeName='$tipoIdentificadorFiscalCustomer'>$nitCustomer</cbc:CompanyID>
         </cac:PartyLegalEntity>
      </cac:Party>
   </cac:AccountingCustomerParty>";

        $string .= "<cac:TaxRepresentativeParty>
      <cac:PartyIdentification>
         <cbc:ID schemeAgencyID='195' schemeAgencyName='CO, DIAN (Dirección de Impuestos y Aduanas Nacionales)' schemeID='$dvCustomer' schemeName='$tipoIdentificadorFiscalCustomer'>$nitCustomer</cbc:ID>
      </cac:PartyIdentification>
   </cac:TaxRepresentativeParty>";

        return $string;
    }

    public function formTotalsXML($formaPago, $medioPago, $totalValorBruto, $totalBaseTributo, $totalValorBrutoMasTributo, $valorTotalFactura, $productos, $otrosImpuestos, $fechaVencimientoFactura){
        if($formaPago == 1){
            $string = "<cac:PaymentMeans>
            <cbc:ID>$formaPago</cbc:ID>
            <cbc:PaymentMeansCode>$medioPago</cbc:PaymentMeansCode>
        </cac:PaymentMeans>";
        } else{
            $string = "<cac:PaymentMeans>
            <cbc:ID>$formaPago</cbc:ID>
            <cbc:PaymentMeansCode>$medioPago</cbc:PaymentMeansCode>
            <cbc:PaymentDueDate>$fechaVencimientoFactura</cbc:PaymentDueDate>
        </cac:PaymentMeans>";
        }
        $stringIva = "";
        $productosConIva = false;
        $productosConOtroImpuesto = false;
        $taxTotal = true;
        $totalValorIvas = 0;
        foreach($productos as $producto){ // para IVAs
            // $valorProducto = $this->formatoMonedaDian($producto['valorTotal']);
            $valorIva = 0;
            if($producto['declaraIva']){
                if($taxTotal){
                    $stringIva .= "<cac:TaxTotal>";
                    $taxTotal = false;
                }
            $valorProducto = $this->formatoMonedaDian($producto['valorBruto']);
            $porcentajeIva = 19;
            $porcentajeNumber = number_format($porcentajeIva, 2);
            $valorIva = $producto['declaraIva'] ? ($valorProducto * ($porcentajeIva / 100)) : 0;  // Si no declara IVA, se asigna 0 // Si declara IVA, se calcula el 19%
            $valorIva = $this->formatoMonedaDian($valorIva);
            $stringIva .= "<cac:TaxSubtotal>
                <cbc:TaxableAmount currencyID='COP'>$valorProducto</cbc:TaxableAmount>
                <cbc:TaxAmount currencyID='COP'>$valorIva</cbc:TaxAmount>
                <cac:TaxCategory>
                    <cbc:Percent>$porcentajeNumber</cbc:Percent>
                    <cac:TaxScheme>
                        <cbc:ID>01</cbc:ID>
                        <cbc:Name>IVA</cbc:Name>
                    </cac:TaxScheme>
                </cac:TaxCategory>
            </cac:TaxSubtotal>";
            $productosConIva = true;
            }
            $totalValorIvas += $valorIva;
        }
        if($productosConIva){
            $stringIva .= "</cac:TaxTotal>";
        }
        $stringImpuesto = "";

        foreach($otrosImpuestos as $impuesto){ // para Otros Impuestos (ICA, INC, etc)
            $productosConOtroImpuesto = true;
            $valorTotalImpuesto = $this->formatoMonedaDian($impuesto['valorImpuesto']);
            $valorBaseImpuesto = $this->formatoMonedaDian($impuesto['valorBruto']);
            $porcentajeImpuesto = number_format($impuesto['porcentaje'], 2);
            $nombreImpuesto = $impuesto['nombre'];
            $idImpuesto = $impuesto['id'];
            log::info("Porcentaje del impuesto: " . $impuesto['nombre'] . " es: " . $porcentajeImpuesto);

            $stringImpuesto .= "<cac:TaxTotal>
                <cbc:TaxAmount currencyID='COP'>$valorTotalImpuesto</cbc:TaxAmount>
                <cac:TaxSubtotal>
                    <cbc:TaxableAmount currencyID='COP'>$valorBaseImpuesto</cbc:TaxableAmount>
                    <cbc:TaxAmount currencyID='COP'>$valorTotalImpuesto</cbc:TaxAmount>
                    <cac:TaxCategory>
                        <cbc:Percent>$porcentajeImpuesto</cbc:Percent>
                        <cac:TaxScheme>
                            <cbc:ID>$idImpuesto</cbc:ID>
                            <cbc:Name>$nombreImpuesto</cbc:Name>
                        </cac:TaxScheme>
                    </cac:TaxCategory>
                </cac:TaxSubtotal>
            </cac:TaxTotal>";
        }

        // $valorBaseIca = $this->formatoMonedaDian($totalBaseTributo);
        // $valorBaseInc = $this->formatoMonedaDian($totalBaseTributo);

        // $stringIca = "<cac:TaxTotal>
        //     <cbc:TaxAmount currencyID='COP'>$valorTotalIca</cbc:TaxAmount>
        //     <cac:TaxSubtotal>
        //         <cbc:TaxableAmount currencyID='COP'>$valorBaseIca</cbc:TaxableAmount>
        //         <cbc:TaxAmount currencyID='COP'>$valorTotalIca</cbc:TaxAmount>
        //         <cac:TaxCategory>
        //             <cbc:Percent>$porcentajeIca</cbc:Percent>
        //             <cac:TaxScheme>
        //                 <cbc:ID>03</cbc:ID>
        //                 <cbc:Name>ICA</cbc:Name>
        //             </cac:TaxScheme>
        //         </cac:TaxCategory>
        //     </cac:TaxSubtotal>
        // </cac:TaxTotal>";

        // $stringInc = "<cac:TaxTotal>
        //     <cbc:TaxAmount currencyID='COP'>$valorTotalInc</cbc:TaxAmount>
        //     <cac:TaxSubtotal>
        //         <cbc:TaxableAmount currencyID='COP'>$valorBaseInc</cbc:TaxableAmount>
        //         <cbc:TaxAmount currencyID='COP'>$valorTotalInc</cbc:TaxAmount>
        //         <cac:TaxCategory>
        //             <cbc:Percent>$porcentajeInc</cbc:Percent>
        //             <cac:TaxScheme>
        //                 <cbc:ID>04</cbc:ID>
        //                 <cbc:Name>INC</cbc:Name>
        //             </cac:TaxScheme>
        //         </cac:TaxCategory>
        //     </cac:TaxSubtotal>
        // </cac:TaxTotal>";

        if($productosConIva){
            $string .= "<cbc:TaxAmount currencyID='COP'>$totalValorIvas</cbc:TaxAmount>";
        }

        if($productosConOtroImpuesto == false && $productosConIva == false){
            $totalBaseTributo = "0.00";
        }

        $string .=  $stringIva . $stringImpuesto .
            "<cac:LegalMonetaryTotal>
            <cbc:LineExtensionAmount currencyID='COP'>$totalValorBruto</cbc:LineExtensionAmount>
            <cbc:TaxExclusiveAmount currencyID='COP'>$totalBaseTributo</cbc:TaxExclusiveAmount>
            <cbc:TaxInclusiveAmount currencyID='COP'>$totalValorBrutoMasTributo</cbc:TaxInclusiveAmount>
            <cbc:PayableAmount currencyID='COP'>$valorTotalFactura</cbc:PayableAmount>
        </cac:LegalMonetaryTotal>";

        return $string;
    }

    public function formLinesXML($productos, $otrosImpuestos){
        $string = "";
        foreach($productos as $producto){
            $numeroLinea = $producto['numeroLinea'];
            $cantidadProducto = $producto['cantidadProducto'];
            $codigoUnidadCantidad = $producto['codigoUnidadCantidad'];
            // $valorTotalLinea = $this->formatoMonedaDian($producto['valorBruto']);
            $valorTotalLinea = $this->formatoMonedaDian($producto['valorBruto']);
            $itemDescripcion = $producto['itemDescripcion'];
            $idItemSeller = $producto['idItemSeller'];
            $codigoUnidadCantidadItem = $producto['codigoUnidadCantidadItem'];
            $valorProducto = $this->formatoMonedaDian($producto['valorBruto']);

            $porcentajeIva = 19;
            $porcentajeNumber = number_format($porcentajeIva, 2);
            $valorIva = $producto['declaraIva'] ? ($valorProducto * ($porcentajeIva / 100)) : 0;  // Si no declara IVA, se asigna 0 // Si declara IVA, se calcula el 19%
            $valorIva = $this->formatoMonedaDian($valorIva);

            $string .= "<cac:InvoiceLine>
            <cbc:ID>$numeroLinea</cbc:ID>
            <cbc:InvoicedQuantity unitCode='$codigoUnidadCantidad'>$cantidadProducto</cbc:InvoicedQuantity>
            <cbc:LineExtensionAmount currencyID='COP'>$valorTotalLinea</cbc:LineExtensionAmount>";

            if($producto['declaraIva']){
                $string .= "<cac:TaxTotal>
                <cbc:TaxAmount currencyID='COP'>$valorIva</cbc:TaxAmount>
                <cac:TaxSubtotal>
                    <cbc:TaxableAmount currencyID='COP'>$valorTotalLinea</cbc:TaxableAmount>
                    <cbc:TaxAmount currencyID='COP'>$valorIva</cbc:TaxAmount>
                    <cac:TaxCategory>
                    <cbc:Percent>$porcentajeNumber</cbc:Percent>
                    <cac:TaxScheme>
                        <cbc:ID>01</cbc:ID>
                        <cbc:Name>IVA</cbc:Name>
                    </cac:TaxScheme>
                    </cac:TaxCategory>
                </cac:TaxSubtotal>
            </cac:TaxTotal>";
            }

            $stringImpuesto = "";

            foreach($otrosImpuestos as $impuesto){ // para Otros Impuestos (ICA, INC, etc)
                $porcentajeImpuesto = $impuesto['porcentaje'];
                $valorTotalLineaImpuesto = $this->formatoMonedaDian($producto['valorBruto']);
                $valorImpuesto = $valorProducto * ($porcentajeImpuesto / 100);  // Si no declara IVA, se asigna 0 // Si declara IVA, se calcula el 19%
                $valorImpuesto = $this->formatoMonedaDian($valorImpuesto);
                $porcentajeNumberImpuesto = number_format($porcentajeImpuesto, 2);
                $nombreImpuesto = $impuesto['nombre'];
                $idImpuesto = $impuesto['id'];

                $stringImpuesto .= "<cac:TaxTotal>
                    <cbc:TaxAmount currencyID='COP'>$valorImpuesto</cbc:TaxAmount>
                    <cac:TaxSubtotal>
                        <cbc:TaxableAmount currencyID='COP'>$valorTotalLineaImpuesto</cbc:TaxableAmount>
                        <cbc:TaxAmount currencyID='COP'>$valorImpuesto</cbc:TaxAmount>
                        <cac:TaxCategory>
                        <cbc:Percent>$porcentajeNumberImpuesto</cbc:Percent>
                        <cac:TaxScheme>
                            <cbc:ID>$idImpuesto</cbc:ID>
                            <cbc:Name>$nombreImpuesto</cbc:Name>
                        </cac:TaxScheme>
                        </cac:TaxCategory>
                    </cac:TaxSubtotal>
                </cac:TaxTotal>";
            }

            $string .= $stringImpuesto . "<cac:Item>
                <cbc:Description>$itemDescripcion</cbc:Description>
                <cac:AdditionalItemIdentification>
                    <cbc:ID schemeID='999' schemeName='EAN13'>$idItemSeller</cbc:ID>
                </cac:AdditionalItemIdentification>
            </cac:Item>
            <cac:Price>
                <cbc:PriceAmount currencyID='COP'>$valorProducto</cbc:PriceAmount>
                <cbc:BaseQuantity unitCode='$codigoUnidadCantidadItem'>$cantidadProducto</cbc:BaseQuantity>
            </cac:Price>
        </cac:InvoiceLine>";
        }

//         $string = "<cac:InvoiceLine>
//       <cbc:ID>$numeroLinea</cbc:ID>
//       <cbc:InvoicedQuantity unitCode='$codigoUnidadCantidad'>$cantidadProducto</cbc:InvoicedQuantity>
//       <cbc:LineExtensionAmount currencyID='COP'>$valorTotalLinea</cbc:LineExtensionAmount>
//       <cac:Item>
//          <cbc:Description>$itemDescripcion</cbc:Description>
//          <cac:AdditionalItemIdentification>
//             <cbc:ID schemeID='999' schemeName='EAN13'>$idItemSeller</cbc:ID>
//          </cac:AdditionalItemIdentification>
//       </cac:Item>
//       <cac:Price>
//          <cbc:PriceAmount currencyID='COP'>$valorTotalLinea</cbc:PriceAmount>
//          <cbc:BaseQuantity unitCode='$codigoUnidadCantidadItem'>$cantidadItem</cbc:BaseQuantity>
//       </cac:Price>
//    </cac:InvoiceLine>";

        return $string;
    }

    public function generarCUFE($facturaId, $fechaEmision, $horaEmision, $valorTotal, $valorSinImpuestosFactura, $valorImpuestoIva, $valorImpuestoConsumo, $valorImpuestoIca, $companyNit, $cedulaCliente, $tipoAmbiente, $claveTecnica2){

        $NumFac = $facturaId;
        $FecFac = $fechaEmision;
        $HorFac = $horaEmision;
        $ValFac = $valorSinImpuestosFactura;
        $Codimp1 = '01';
        $ValImp1 = $valorImpuestoIva;
        $CodImp2 = '04';
        $ValImp2 = $valorImpuestoConsumo;
        $CodImp3 = '03';
        $ValImp3 = $valorImpuestoIca;
        $ValTot = $valorTotal;
        $NitFE = $companyNit;
        $NumAdq = $cedulaCliente;
        $claveTecnica = $claveTecnica2;
        $tipoAmbiente1 = $tipoAmbiente;

        $CUFE = $NumFac . $FecFac . $HorFac . $ValFac . $Codimp1 . $ValImp1 . $CodImp2 . $ValImp2 . $CodImp3 . $ValImp3 . $ValTot . $NitFE . $NumAdq . $claveTecnica . $tipoAmbiente1;

        $UUID = hash('sha384', $CUFE);

        return $UUID;
    }

    public function generarHuellaSoftware($facturaId, $SoftwarePin, $SoftwareId){

        $SoftwareSecurityCode = hash('sha384', $SoftwareId . $SoftwarePin . $facturaId);

        return $SoftwareSecurityCode;
    }

    public function generarQR($NumFac, $FecFac, $HorFac, $NitFac, $DocAdq, $ValFac, $ValIva, $ValOtroIm, $ValTolFac, $CUFE, $tipoAmbiente){
        if($tipoAmbiente == 1){ // Producción
            $url = "https://catalogo-vpfe.dian.gov.co/document/searchqr?documentkey=$CUFE";
        } else{
            $url = "https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey=$CUFE";
        }

        $qrcode = "NumFac=$NumFac
        FecFac=$FecFac
        HorFac=$HorFac
        NitFac=$NitFac
        DocAdq=$DocAdq
        ValFac=$ValFac
        ValIva=$ValIva
        ValOtroIm=$ValOtroIm
        ValTolFac=$ValTolFac
        CUFE=$CUFE
        URL=$url";

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

    public function ivasProductos($productos){
        $totalValorIvas = 0;
        foreach($productos as $producto){
            $valorProducto = $this->formatoMonedaDian($producto['valorTotal']);
            $porcentajeIva = 19;
            $valorIva = $producto['declaraIva'] ? ($valorProducto * ($porcentajeIva / 100)) : 0;  // Si no declara IVA, se asigna 0 // Si declara IVA, se calcula el 19%
            $valorIva = $this->formatoMonedaDian($valorIva);

            $totalValorIvas += $valorIva;
        }

        return $totalValorIvas;
    }
}
