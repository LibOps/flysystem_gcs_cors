<?php

namespace Drupal\flysystem_gcs_cors\Ajax;

use Drupal\Core\Ajax\AjaxResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Site\Settings;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Google\Cloud\Storage\StorageClient;
use Drupal\file\Entity\File;

class Gcs extends AjaxResponse {

  /**
   * {@inheritdoc}
   */
  public function __construct($data = null, $status = 200, $headers = []) {
    parent::__construct($data, $status, $headers);

    $this->mimeType = \Drupal::service('file.mime_type.guesser');
    $this->entityFieldManager = \Drupal::service('entity_field.manager');
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->moduleHandler = \Drupal::service('module_handler');
  }


  public function getSignedUrl($entity_type, $bundle, $entity_id, $field, $delta, $file_name) {
    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    $file_directory_untokenized = $fields[$field]->getSetting('file_directory');
    $scheme = $fields[$field]->getSetting('uri_scheme');
    $flysystem_settings = Settings::get('flysystem', []);
    $config = $flysystem_settings[$scheme]['config'];
    $bucket_name = $config['bucket'];

    $storage = new StorageClient($config);
    $bucket = $storage->bucket($bucket_name);

    $validFor = new \DateTime('10 min');
    $response = $bucket->generateSignedPostPolicyV4(
      $this->getDirectory($file_directory_untokenized, $entity_type, $entity_id) . '/' . $file_name,
      $validFor
    );

    return new JsonResponse($response);
  }

  public function saveFile($entity_type, $bundle, $entity_id, $field, $delta, $file_name, $file_size) {
    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    $file_directory_untokenized = $fields[$field]->getSetting('file_directory');

    $file_mime = $this->mimeType->guess($file_name);
    $values = [
      'uid' => \Drupal::currentUser()->id(),
      'status' => 0,
      'filename' => $file_name,
      'uri' => $fields[$field]->getSetting('uri_scheme') . '://' . $this->getDirectory($file_directory_untokenized, $entity_type, $entity_id) . '/' . $file_name,
      'filesize' => $file_size,
      'filemime' => $file_mime,
      'source' => $field_name,
    ];
    $file = File::create($values);

    $errors = [];
    $errors = array_merge($errors, $this->moduleHandler->invokeAll('file_validate', [$file]));

    if (empty($errors)) {
      $file->save();
      $values['fid'] = $file->id();
      $values['uuid'] = $file->uuid();
    }
    else {
      $file->delete();
      $values['errmsg'] = implode("\n", $errors);
    }

    return new JsonResponse($values);
  }

  /**
   * Custom access function for AJAX routes
   */
  public function access($entity_type, $bundle, $entity_id, $field, $delta, $file_name, $file_size = FALSE) {
    $access_controller =  $this->entityTypeManager->getAccessControlHandler($entity_type);
    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);

    // make sure the account has access to edit or create the entity type
    if ($entity_id) {
      $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
      $has_access = $entity && $access_controller->access($entity, 'update');
    }
    else {
      $has_access = $access_controller->createAccess($bundle);
    }

    // make sure the file extension is allowed
    $extension = substr($file_name, strrpos($file_name, '.') + 1);
    $extensions = $fields[$field]->getSetting('file_extensions');
    $extensions = explode(' ', $extensions);

    return AccessResult::allowedIf($has_access &&
      isset($fields[$field]) &&
      $access_controller->fieldAccess('edit', $fields[$field]) &&
      in_array($extension, $extensions));
  }

  private function getDirectory($file_directory_untokenized, $entity_type, $entity_id) {
    $token_service = \Drupal::token();
    $data = [];
    if ($entity_id) {
      $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
      if ($entity) {
        $data[$entity_type] = $entity;
      }
    }
    return \Drupal::token()->replace($file_directory_untokenized, $data);
  }
}
