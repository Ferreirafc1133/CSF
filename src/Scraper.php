<?php

declare(strict_types=1);

namespace PhpCfdi\CsfPdfScraper;

use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\TimeoutException;
use GuzzleHttp\Client;
use PhpCfdi\CsfPdfScraper\Contracts\BrowserClientInterface;
use PhpCfdi\CsfPdfScraper\Exceptions\InvalidCaptchaException;
use PhpCfdi\CsfPdfScraper\Exceptions\InvalidCredentialsException;
use PhpCfdi\CsfPdfScraper\Exceptions\PDFDownloadException;
use PhpCfdi\CsfPdfScraper\Exceptions\SatScraperException;
use PhpCfdi\ImageCaptchaResolver\CaptchaImage;
use PhpCfdi\ImageCaptchaResolver\CaptchaResolverInterface;
use Symfony\Component\Panther\PantherTestCase;
use GuzzleHttp\Cookie\CookieJar;

class Scraper
{
    public function __construct(
        private Credentials $credentials,
        private CaptchaResolverInterface $captchaResolver,
        private BrowserClientInterface $browserClient,
        private Client $client,
        private int $timeout = 30
    ) {
    }

    private function resolveCaptcha(string $captchaUrl): string
    {
        $captchaImage = file_get_contents($captchaUrl);
        $captchaPath = __DIR__ . '/captcha.png';
        file_put_contents($captchaPath, $captchaImage);
        echo "Por favor, resuelve el captcha guardado en: $captchaPath\n";
        echo "Ingresa el valor del captcha: ";
        return trim(fgets(STDIN));
    }

    private function login(): void
    {
        $this->browserClient->get(URL::LOGIN_URL);
        try {
            $this->browserClient->waitFor('#divCaptcha', $this->timeout);
        } catch (TimeoutException | NoSuchElementException $exception) {
            throw new SatScraperException(sprintf('The %s page does not load as expected', URL::LOGIN_URL), 0, $exception);
        }

        $captcha = $this->browserClient->getCrawler()
            ->filter('#divCaptcha > img')
            ->first();

        $value = $this->resolveCaptcha($captcha->attr('src'));

        echo "RFC: " . $this->credentials->getRfc() . "\n";
        echo "Password: " . $this->credentials->getCiec() . "\n";
        echo "Captcha: " . $value . "\n";

        $form = $this->browserClient->getCrawler()
            ->selectButton('submit')
            ->form();

        $form->setValues([
            'Ecom_User_ID' => $this->credentials->getRfc(),
            'Ecom_Password' => $this->credentials->getCiec(),
            'userCaptcha' => $value,
        ]);

        $this->browserClient->submit($form);

        $html = $this->browserClient->getCrawler()->html();
        if (str_contains($html, 'Captcha no válido')) {
            throw new InvalidCaptchaException('The provided captcha is invalid');
        }

        if (str_contains($html, 'El RFC o contraseña son incorrectos')) {
            throw new InvalidCredentialsException('The provided credentials are invalid');
        }
    }

    private function buildConstancia(): void
    {
        $this->browserClient->get(URL::MAIN_URL);
        try {
            $this->browserClient->waitFor('#idPanelReimpAcuse_header', $this->timeout);
        } catch (TimeoutException | NoSuchElementException $exception) {
            throw new SatScraperException(sprintf('The %s page does not load as expected', URL::MAIN_URL), 0, $exception);
        }

        $form = $this->browserClient->getCrawler()
            ->selectButton('Generar Constancia')
            ->form();

        $this->browserClient->submit($form);
    }

    private function logout(): void
    {
        $this->browserClient->get(URL::LOGOUT_URL);
        $this->browserClient->waitFor('#campo-busqueda', $this->timeout);
        $this->browserClient->getCrawler()
            ->selectButton('Cerrar sesión')
            ->click();
    }

    public function download(): string
    {
        $this->login();
        $this->buildConstancia();

        // Obtener cookies desde el cliente de Panther
        $client = $this->browserClient->getClient();
        $cookies = $client->getCookieJar()->all();

        $cookieJar = new CookieJar();
        foreach ($cookies as $cookie) {
            $cookieJar->setCookie(new \GuzzleHttp\Cookie\SetCookie([
                'Name' => $cookie->getName(),
                'Value' => $cookie->getValue(),
                'Domain' => $cookie->getDomain(),
                'Path' => $cookie->getPath(),
                'Expires' => $cookie->getExpiresTime(),
                'Secure' => $cookie->isSecure(),
                'HttpOnly' => $cookie->isHttpOnly(),
            ]));
        }

        try {
            $response = $this->client->request('GET',
                URL::DOWNLOAD_CONSTANCIA_URL, [
                    'cookies' => $cookieJar,
                    'verify' => false, // Desactiva la verificación de SSL
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                        'Accept' => 'application/pdf',
                    ],
                ]);
        } catch (\Throwable $exception) {
            file_put_contents('error_log.txt', $exception->getMessage(), FILE_APPEND);
            file_put_contents('error_log.txt', "\n", FILE_APPEND);
            file_put_contents('error_log.txt', $exception->getTraceAsString(), FILE_APPEND);
            file_put_contents('error_log.txt', "\n\n", FILE_APPEND);
            throw new PDFDownloadException('Error getting pdf, server error', 0, $exception);
        }

        @unlink('SAT.pdf');

        return $response->getBody()->__toString();
    }
}
