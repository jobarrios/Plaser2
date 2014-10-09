<?php
/*****
PLASER_SoapFault class
*****/

class PLASER_SoapFault
{
	public function __construct($faultcode=null, $faultstring=null, $faultactor=null, $detail=null)
	{
		if(!$faultcode) $faultcode = 'Server';
		if(!strstr($faultcode, 'SOAP-ENV:')) $faultcode = 'SOAP-ENV:'.$faultcode;

		$soapFault = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
		$soapFault.= '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">';
		$soapFault.= '<SOAP-ENV:Body>';
		$soapFault.= '<SOAP-ENV:Fault>';
		$soapFault.= '<faultcode>'.utf8_encode(htmlspecialchars($faultcode, ENT_QUOTES, "UTF-8")).'</faultcode>';
		$soapFault.= '<faultstring>'.utf8_encode(htmlspecialchars($faultstring, ENT_QUOTES, "UTF-8")).'</faultstring>';
		$soapFault.= '<faultactor>'.utf8_encode(htmlspecialchars($faultactor, ENT_QUOTES, "UTF-8")).'</faultactor>';
		$soapFault.= '<detail>'.utf8_encode(htmlspecialchars($detail, ENT_QUOTES, "UTF-8")).'</detail>';
		$soapFault.= '</SOAP-ENV:Fault>';
		$soapFault.= '</SOAP-ENV:Body>';
		$soapFault.= '</SOAP-ENV:Envelope>';

		header('Internal Server Error', true, 500);
		header('Content-Length: '.strlen($soapFault));
		header('Content-Type: text/xml; charset="utf-8"');
		echo($soapFault);
	}
}
?>
