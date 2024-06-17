<?php

declare(strict_types=1);

require "vendor/autoload.php";

use PhpCfdi\CsfPdfScraper\Credentials;
use PhpCfdi\CsfPdfScraper\Scraper;
use PhpCfdi\ImageCaptchaResolver\Resolvers\ConsoleResolver;
use Symfony\Component\Panther\Client;
use PhpCfdi\CsfPdfScraper\PantherBrowserClient;
use Dotenv\Dotenv;

// Cargar las variables de entorno desde el archivo .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Crear las credenciales usando las variables de entorno
$credentials = new Credentials(
    $_ENV['CSF_USERNAME'],
    $_ENV['CSF_PASSWORD']
);

// Crear las instancias necesarias
$chromeClient = Client::createChromeClient();
$client = new PantherBrowserClient($chromeClient);
$http = new \GuzzleHttp\Client();
$resolver = new ConsoleResolver(); // Remover el ConsoleResolver si no se usa

// Crear el scraper con las dependencias
$scraper = new Scraper($credentials, $resolver, $client, $http);

// Ejecutar el scraper para descargar el PDF
$pdfContents = $scraper->download();

// Guardar el PDF descargado en un archivo
file_put_contents('./constancia.pdf', $pdfContents);

echo "Constancia descargada con éxito.\n";
