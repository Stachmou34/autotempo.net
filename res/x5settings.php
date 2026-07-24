<?php

/*
|-------------------------------
|	GENERAL SETTINGS
|-------------------------------
*/

$imSettings['general'] = array(
	'url' => 'https://autotempo.net/',
	'homepage_url' => 'https://autotempo.net/index.html',
	'icon' => '',
	'version' => '2019.3.11.0',
	'sitename' => 'Assurance Temporaire',
	'lang_code' => 'fr-FR',
	'public_folder' => '',
	'salt' => '5t349btxt8y6klijh41mqw32s4oz7214c22i5woz13r3mw8c3x71pv59q1b5',
	'use_common_email_sender_address' => false,
	'common_email_sender_addres' => ''
);
/*
|-------------------------------
|	PASSWORD POLICY
|-------------------------------
*/

$imSettings['password_policy'] = array(
	'required_policy' => false,
	'minimum_characters' => '6',
	'include_uppercase' => false,
	'include_numeric' => false,
	'include_special' => false,
);
/*
|-------------------------------
|	Captcha
|-------------------------------
*/ImTopic::$captcha_code = "		<div class=\"x5captcha-wrap\">
			<label>Code de sécurité :</label><br />
			<input type=\"text\" class=\"imCpt\" name=\"imCpt\" maxlength=\"5\" />
		</div>
";


$imSettings['admin'] = array(
	'icon' => 'admin/images/logo_za2yhbfj.png',
	'notification_public_key' => '421564baf5dff76b',
	'notification_private_key' => '318126756e58473d',
	'enable_manager_notifications' => false,
	'theme' => 'orange',
	'extra-dashboard' => array(),
	'extra-links' => array()
);


/*
|--------------------------------------------------------------------------------------
|	DATABASES SETTINGS
|--------------------------------------------------------------------------------------
*/

$imSettings['databases'] = array();
$ecommerce = Configuration::getCart();
// Setup the coupon data
$couponData = array();
$couponData['products'] = array();
// Setup the cart
$ecommerce->setPublicFolder('');
$ecommerce->setCouponData($couponData);
$ecommerce->setSettings(array(
	'force_sender' => false,
	'email_opening' => 'Cher/chère client(e),<br /><br />Nous vous remercions de votre commande. Nous vous rappelons que le paiement n\'a pas encore été reçu.<br /><br />Vous trouverez ci-dessous la liste des produits commandés, les informations de facturation et de livraison les instructions pour effectuer le paiement.',
	'email_closing' => 'Nous contacter pour obtenir de plus amples informations.<br /><br />Nos meilleures salutations, Service commercial.',
	'email_payment_opening' => 'Cher/chère client(e),<br /><br />Nous vous remercions de votre achat, on vous confirme qu\'on a correctement reçu le paiement et que votre commande sera traitée dès que possible.<br /><br />Vous trouverez ci-dessous la liste des produits commandés et les informations de facturation et de livraison.',
	'email_payment_closing' => 'Nous restons à votre disposition pour toute information supplémentaire.<br /><br />Cordialement,<br />l’Équipe Commerciale<br />',
	'email_digital_shipment_opening' => 'Bonjour,<br /><br />Nous vous remercions pour votre achat et nous avons le plaisir de vous envoyer la liste des liens de téléchargement correspondant aux produits commandés :<br />',
	'email_digital_shipment_closing' => 'Nous restons à votre disposition pour toute information supplémentaire.<br /><br />Cordialement,<br />l’Équipe Commerciale<br />',
	'email_physical_shipment_opening' => 'Bonjour,<br /><br />Nous vous remercions pour votre achat et nous avons le plaisir de vous envoyer la liste des produits envoyés :',
	'email_physical_shipment_closing' => 'Nous restons à votre disposition pour toute information supplémentaire.<br /><br />Cordialement,<br />l’Équipe Commerciale',
	'sendEmailBeforePayment' => true,
	'sendEmailAfterPayment' => false,
	'useCSV' => false,
	'header_bg_color' => 'rgba(37, 58, 88, 1)',
	'header_text_color' => 'rgba(255, 255, 255, 1)',
	'cell_bg_color' => 'rgba(255, 255, 255, 1)',
	'cell_text_color' => 'rgba(0, 0, 0, 1)',
	'availability_reduction_type' => 1,
	'border_color' => 'rgba(211, 211, 211, 1)',
	'owner_email' => 'example@example.com',
	'vat_type' => 'included'
));

$ecommerce->setPriceFormatData(array(
	'decimals' => 2,
	'decimal_sep' => '.',
	'thousands_sep' => '',
	'currency_to_right' => true,
	'currency_separator' => ' ',
	'show_zero_as' => '0',
	'currency_symbol' => '€',
	'currency_code' => 'EUR',
	'currency_name' => 'Euro',
));

$ecommerce->setProductsData(array());
$ecommerce->setPaymentData(array(
	'8dkejfu5' => array(
		'id' => '8dkejfu5',
		'name' => 'Virement bancaire',
		'description' => 'Payer plus tard par virement bancaire.',
		'email_text' => 'Les données requises pour le paiement par virement bancaire sont les suivantes :

XXX YYY ZZZ

Remarque : au terme du paiement, il est nécessaire d\'envoyer une copie du reçu avec le Numéro de la commande.',
		'enableAfterPaymentEmail' => false
	)));
$ecommerce->setShippingData(array(
	'j48dn4la' => array(
		'id' => 'j48dn4la',
		'name' => 'Courrier',
		'description' => 'Les produits seront livrés dans 3-5 jours.',
		'email_text' => 'Expédition par Courrier.\\nLes produits seront livrés dans 3-5 jours.'
	),
	'hdj47dut' => array(
		'id' => 'hdj47dut',
		'name' => 'Livraison express',
		'description' => 'Les produits seront livrés dans 1-2 jours.',
		'email_text' => 'Expédition par Livraison express.\\nLes produits seront livrés dans 1-2 jours.'
	)));

/*
|-------------------------------------------------------------------------------------------
|	GUESTBOOK SETTINGS
|-------------------------------------------------------------------------------------------
*/

$imSettings['guestbooks'] = array();

/*
|-------------------------------------------------------------------------------------------
|	Dynamic Objects SETTINGS
|-------------------------------------------------------------------------------------------
*/

$imSettings['dynamicobjects'] = array(	'template' => array(
),
	'pages' => array(

	));


/*
|-------------------------------
|	EMAIL SETTINGS
|-------------------------------
*/

$ImMailer->emailType = 'phpmailer';
$ImMailer->exposeWsx5 = true;
$ImMailer->header = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">' . "\n" . '<html>' . "\n" . '<head>' . "\n" . '<meta http-equiv="content-type" content="text/html; charset=utf-8">' . "\n" . '<meta name="generator" content="Incomedia WebSite X5 Professional 2019.3.11 - www.websitex5.com">' . "\n" . '</head>' . "\n" . '<body bgcolor="#37474F" style="background-color: #37474F;">' . "\n\t" . '<table border="0" cellpadding="0" align="center" cellspacing="0" style="padding: 0; margin: 0 auto; width: 700px;">' . "\n\t" . '<tr><td id="imEmailContent" style="min-height: 300px; padding: 10px; font: normal normal normal 9pt \'Roboto\'; color: #000000; background-color: #FFFFFF; text-decoration: none; text-align: left;  width: 700px;border-style: solid; border-color: #000000; border-top-width: 1px; border-right-width: 1px; border-bottom-width: 1px; border-left-width: 1px;background-color: #FFFFFF" width="700px">' . "\n\t\t";
$ImMailer->footer = "\n\t" . '</td></tr>' . "\n\t" . '</table>' . "\n" . '<table width="100%"><tr><td id="imEmailFooter" style="font: normal normal normal 7pt \'Roboto\'; color: #FFFFFF; background-color: transparent; text-decoration: none; text-align: center;  padding: 10px; margin-top: 5px;background-color: transparent">' . "\n\t\t" . 'Ce courriel fournit des informations destinées uniquement au destinataire susmentionné.<br>Si vous n\'êtes par le destinataire de ce message, veuillez le signaler immédiatement à l\'expéditeur et le détruire, sans le copier.' . "\n\t" . '</td></tr></table>' . "\n\t" . '</body>' . "\n" . '</html>';
$ImMailer->bodyBackground = '#FFFFFF';
$ImMailer->bodyBackgroundEven = '#FFFFFF';
$ImMailer->bodyBackgroundOdd = '#F0F0F0';
$ImMailer->bodyBackgroundBorder = '#CDCDCD';
$ImMailer->bodyTextColorOdd = '#000000';
$ImMailer->bodySeparatorBorderColor = '#000000';
$ImMailer->emailBackground = '#37474F';
$ImMailer->emailContentStyle = 'font: normal normal normal 9pt \'Roboto\'; color: #000000; background-color: #FFFFFF; text-decoration: none; text-align: left; ';
$ImMailer->emailContentFontFamily = 'font-family: Roboto;';

// End of file x5settings.php