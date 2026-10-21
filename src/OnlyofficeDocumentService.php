<?php

namespace Drupal\onlyoffice;

/**
 * Copyright (c) Ascensio System SIA 2026.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

use Drupal\Core\Config\ConfigFactoryInterface;
use Firebase\JWT\JWT;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Sends the requests from Drupal to ONLYOFFICE Docs.
 */
class OnlyofficeDocumentService {

  /**
   * Timeout of the requests to Document Server, in seconds.
   */
  private const TIMEOUT = 10;

  /**
   * Timeout of the conversion request to Document Server, in seconds.
   */
  private const CONVERT_TIMEOUT = 30;

  /**
   * Timeout of the file download from Document Server, in seconds.
   */
  private const DOWNLOAD_TIMEOUT = 60;

  /**
   * Lifetime of the request tokens, in seconds.
   */
  private const JWT_EXPIRATION = 300;

  /**
   * The message of the failed request to Document Server.
   */
  private const ERROR_MESSAGE = 'Error occurred in the document service';

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a OnlyofficeDocumentService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
    RequestStack $request_stack,
    LoggerInterface $logger,
  ) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->requestStack = $request_stack;
    $this->logger = $logger;
  }

  /**
   * Check the connection between Drupal and Document Server.
   *
   * @return string
   *   The Document Server version.
   *
   * @throws \RuntimeException
   *   When a step of the check fails, with the reason as the message.
   */
  public function checkConnection(): string {
    $docsUrl = (string) $this->configFactory->get('onlyoffice.settings')->get('doc_server_url');

    if ($this->requestStack->getCurrentRequest()->isSecure() && preg_match('/^http:\/\//i', $docsUrl)) {
      throw new \RuntimeException('Mixed Active Content is not allowed. HTTPS address for ONLYOFFICE Docs is required.');
    }

    if (!$this->healthcheck()) {
      throw new \RuntimeException('Bad healthcheck status');
    }

    $version = (string) ($this->command(['c' => 'version'])['version'] ?? '');

    if ($version === '') {
      throw new \RuntimeException(self::ERROR_MESSAGE);
    }

    $versionF = floatval($version);
    if ($versionF > 0.0 && $versionF <= 6.0) {
      throw new \RuntimeException('Not supported version');
    }

    $convertedFileUrl = $this->convert(OnlyofficeUrlHelper::getEmptyFileUrl(), 'docx', 'docx', 'check_' . random_int(0, mt_getrandmax()));
    $this->download($convertedFileUrl);

    return $version;
  }

  /**
   * Request health status of Document Server.
   */
  public function healthcheck(): bool {
    $url = rtrim(OnlyofficeUrlHelper::getDocumentServerInternalUrl(), '/') . '/healthcheck';

    try {
      return $this->request('GET', $url) === 'true';
    }
    catch (\RuntimeException $e) {
      return FALSE;
    }
  }

  /**
   * Send command to Document Server.
   *
   * @throws \RuntimeException
   *   When the request fails or Document Server returns an error.
   */
  public function command(array $command): array {
    $url = rtrim(OnlyofficeUrlHelper::getDocumentServerInternalUrl(), '/') . '/command';

    if (!empty($command['key'])) {
      $url .= '?shardkey=' . rawurlencode($command['key']);
    }

    $data = json_decode($this->request('POST', $url, $this->buildJsonOptions($command)), TRUE);

    if (!\is_array($data)) {
      throw new \RuntimeException(self::ERROR_MESSAGE);
    }

    $error = (int) ($data['error'] ?? 0);

    if ($error !== 0) {
      $messages = [
        1 => 'Document key is missing or no document with such key could be found',
        2 => 'Callback url not correct',
        3 => 'Internal server error',
        4 => 'No changes were applied to the document before the forcesave command was received',
        5 => 'Command not correct',
        6 => 'Invalid token',
      ];

      throw new \RuntimeException(self::ERROR_MESSAGE . ': ' . ($messages[$error] ?? 'ErrorCode = ' . $error));
    }

    return $data;
  }

  /**
   * Convert the document on Document Server and return the result URL.
   *
   * @throws \RuntimeException
   *   When the request fails or Document Server returns an error.
   */
  public function convert(string $documentUrl, string $fromExtension, string $toExtension, string $key): string {
    $url = rtrim(OnlyofficeUrlHelper::getDocumentServerInternalUrl(), '/') . '/converter?shardkey=' . rawurlencode($key);

    $body = [
      'async' => FALSE,
      'url' => $documentUrl,
      'outputtype' => $toExtension,
      'filetype' => $fromExtension,
      'title' => $key . '.' . $fromExtension,
      'key' => $key,
    ];

    $options = $this->buildJsonOptions($body);
    $options['timeout'] = self::CONVERT_TIMEOUT;

    $data = json_decode($this->request('POST', $url, $options), TRUE);

    if (!\is_array($data)) {
      throw new \RuntimeException(self::ERROR_MESSAGE);
    }

    $error = (int) ($data['error'] ?? 0);

    if ($error !== 0) {
      $messages = [
        -1 => 'Unknown error',
        -2 => 'Timeout conversion error',
        -3 => 'Conversion error',
        -4 => 'Error while downloading the document file to be converted.',
        -5 => 'Incorrect password',
        -6 => 'Error while accessing the conversion result database',
        -7 => 'Error document request',
        -8 => 'Invalid token',
        -20 => 'Error encrypt signature',
      ];

      throw new \RuntimeException(self::ERROR_MESSAGE . ': ' . ($messages[$error] ?? 'ErrorCode = ' . $error));
    }

    if (empty($data['fileUrl'])) {
      throw new \RuntimeException(self::ERROR_MESSAGE);
    }

    return $data['fileUrl'];
  }

  /**
   * Download file from Document Server through its internal address.
   *
   * @throws \RuntimeException
   *   When the request fails.
   */
  public function download(string $url): string {
    $url = OnlyofficeUrlHelper::replaceDocumentServerUrlToInternal($url);

    return $this->request('GET', $url, ['timeout' => self::DOWNLOAD_TIMEOUT]);
  }

  /**
   * Build options of the JSON request, signed when the secret is set.
   */
  protected function buildJsonOptions(array $data): array {
    $options = [
      'headers' => [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
      ],
    ];

    $secret = $this->configFactory->get('onlyoffice.settings')->get('doc_server_jwt');

    if ($secret) {
      $iat = time();
      $exp = $iat + self::JWT_EXPIRATION;

      $headerToken = JWT::encode(['payload' => $data, 'iat' => $iat, 'exp' => $exp], $secret, 'HS256');
      $options['headers'][OnlyofficeAppConfig::getJwtHeader()] = 'Bearer ' . $headerToken;

      $data['iat'] = $iat;
      $data['exp'] = $exp;
      $data['token'] = JWT::encode($data, $secret, 'HS256');
    }

    $options['body'] = json_encode($data);

    return $options;
  }

  /**
   * Send request to Document Server and return the response body.
   *
   * @throws \RuntimeException
   *   When the request fails.
   */
  protected function request(string $method, string $url, array $options = []): string {
    $options += ['timeout' => self::TIMEOUT];

    try {
      return (string) $this->httpClient->request($method, $url, $options)->getBody();
    }
    catch (\Throwable $e) {
      $this->logger->error('Request to @url failed: @message', ['@url' => $url, '@message' => $e->getMessage()]);
      throw new \RuntimeException(self::ERROR_MESSAGE, 0, $e);
    }
  }

}
