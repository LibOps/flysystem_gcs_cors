<?php

namespace Drupal\flysystem_gcs_cors\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Google\Cloud\Storage\StorageClient;

/**
 * Admin settings form.
 */
class AdminForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'flysystem_gcs_cors.admin',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'flysystem_gcs_cors_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('flysystem_gcs_cors.admin');
    $form['origin'] = [
      '#type' => 'url',
      '#title' => $this->t('Origin'),
      '#description' => $this->t('The origin that will be allowed to PUT to your GCS bucket'),
      '#default_value' => $config->get('origin'),
    ];

    $options = [];
    foreach (Settings::get('flysystem', []) as $scheme => $settings) {
      if ($settings['driver'] === 'gcs') {
        $options[$scheme] = $scheme . ':// -> ' . $settings['config']['bucket'];
      }
    }

    $form['scheme'] = [
      '#type' => 'select',
      '#title' => $this->t('Bucket'),
      '#description' => $this->t('Select which Flysystem GCS bucket to set CORS policy on.'),
      '#options' => $options,
      '#default_value' => $config->get('scheme'),
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $origin = $form_state->getValue('origin');
    $scheme = $form_state->getValue('scheme');
    $settings = Settings::get('flysystem', []);
    $config = $settings[$scheme]['config'];
    $bucket_name = $config['bucket'];

    $this->config('flysystem_gcs_cors.admin')
      ->set('origin', $origin)
      ->set('scheme', $bucket_name)
      ->save();

    $storage = new StorageClient($config);
    $bucket = $storage->bucket($bucket_name);
    if (empty($origin)) {
      $cors = [];
    }
    else {
      $cors = [[
        'method' => ["POST", "PUT"],
        'origin' => [$origin],
        'responseHeader' => [
          'Content-Type',
          'Access-Control-Allow-Origin',
        ],
        'maxAgeSeconds' => 3600,
      ],
      ];
    }
    $bucket->update([
      'cors' => $cors,
    ]);
  }

}
