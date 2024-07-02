<?php

declare(strict_types=1);

require "vendor/autoload.php";

use PhpCfdi\CsfPdfScraper\Credentials;
use PhpCfdi\CsfPdfScraper\Scraper;
use PhpCfdi\ImageCaptchaResolver\Resolvers\ConsoleResolver;
use Symfony\Component\Panther\Client;
use PhpCfdi\CsfPdfScraper\PantherBrowserClient;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$credentials = new Credentials(
    $_ENV['CSF_USERNAME'],
    $_ENV['CSF_PASSWORD']
);

$chromeClient = Client::createChromeClient();
$client = new PantherBrowserClient($chromeClient);
$http = new \GuzzleHttp\Client([
    'verify' => false, // Desactiva la verificación de SSL
]);
$resolver = new ConsoleResolver();

$scraper = new Scraper($credentials, $resolver, $client, $http);

try {
    $pdfContents = $scraper->download();
    file_put_contents('./constancia.pdf', $pdfContents);
    echo "Constancia descargada con éxito.\n";
} catch (Exception $e) {
    echo "Error al descargar la constancia: " . $e->getMessage() . "\n";
    $errorDetails = file_get_contents('error_log.txt');
    echo "Detalles del error: \n" . $errorDetails;
}
