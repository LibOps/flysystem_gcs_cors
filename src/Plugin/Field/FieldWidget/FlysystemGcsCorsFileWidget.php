<?php

namespace Drupal\flysystem_gcs_cors\Plugin\Field\FieldWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Plugin\Field\FieldWidget\FileWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\flysystem_gcs_cors\Element\FlysystemGcsCorsFile;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Plugin implementation of the 'flysystem_gcs_cors_file_widget' widget.
 *
 * @FieldWidget(
 *   id = "flysystem_gcs_cors_file_widget",
 *   label = @Translation("GCS Cors File Upload"),
 *   field_types = {
 *     "file",
 *     "flysystem_gcs_cors_file"
 *   }
 * )
 */
class FlysystemGcsCorsFileWidget extends FileWidget {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element_info = $this->elementInfo->getInfo('flysystem_gcs_cors_file');

    $element['#type'] = 'flysystem_gcs_cors_file';
    $element['#process'][] = $element_info['#process'][0];
    $element['#process'][] = $element['#process'][1];
    $element['#attributes'] = ['class' => ['gcs-cors-file']];

    return $element;
  }

  /**
   * Form API callback. Retrieves the value for the file_generic field element.
   *
   * This method is assigned as a #value_callback in formElement() method.
   */
  public static function value($element, $input = FALSE, FormStateInterface $form_state = NULL) {
    $return = FlysystemGcsCorsFile::valueCallback($element, $input, $form_state);

    // Ensure that all the required properties are returned even if empty.
    $return += [
      'fids' => [],
      'display' => 1,
      'description' => '',
    ];
    return $return;
  }

}
