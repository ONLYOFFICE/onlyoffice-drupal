<?php

namespace Drupal\onlyoffice_connector\Controller;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\onlyoffice_connector\OnlyofficeAppConfig;
use Drupal\onlyoffice_connector\OnlyofficeUrlHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Firebase\JWT\JWT;
/**
 * Returns responses for ONLYOFFICE Connector routes.
 */
class OnlyofficeDownloadController extends ControllerBase {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   * The entity repository.
   */
  public function __construct(EntityRepositoryInterface $entity_repository) {
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository')
    );
  }

  public function download($key, Request $request) {

    if (\Drupal::config('onlyoffice_connector.settings')->get('doc_server_jwt')) {
      $jwtHeader = OnlyofficeAppConfig::getJwtHeader();
      $header = $request->headers->get($jwtHeader);
      $token = $header !== NULL ?  substr($header, strlen("Bearer ")) : $header;

      if (empty($token)) {
        throw new UnauthorizedHttpException("Download without jwt");
      }

      try {
        JWT::decode($token, \Drupal::config('onlyoffice_connector.settings')->get('doc_server_jwt'), array("HS256"));
      } catch (\Exception $e) {
        throw new UnauthorizedHttpException("Download with invalid jwt");
      }
    }

    $linkParameters = OnlyofficeUrlHelper::verifyLinkKey($key);

    if(!$linkParameters) {
      throw new BadRequestHttpException('Invalid link key.');
    }

    $uuid = $linkParameters[0];
    $userId = $linkParameters[1]; //ToDo : check access

    if (!$uuid || !Uuid::isValid($uuid)) {
      throw new BadRequestHttpException('Invalid parameter UUID.');
    }

    $file = $this->entityRepository->loadEntityByUuid('file', $uuid);

    if (!$file) {
      throw new BadRequestHttpException("The targeted resource with UUID `{$uuid}` does not exist.");
    }

    return new BinaryFileResponse($file->getFileUri(), 200);
  }

}
