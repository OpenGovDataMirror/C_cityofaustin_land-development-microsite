<?php

/**
 * @file
 * Webform module translation hooks.
 */

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Utility\WebformYaml;
use Drupal\Core\Serialization\Yaml;
use Drupal\webform\Utility\WebformElementHelper;

/**
 * Implements hook_form_FORM_ID_alter().
 */
function webform_form_locale_translate_edit_form_alter(&$form, FormStateInterface $form_state) {
  // Don't allow YAML to be validated using locale string translation.
  foreach (Element::children($form['strings']) as $key) {
    $element =& $form['strings'][$key];
    if ($element['original']
      && !empty($element['original']['#plain_text'])
      && preg_match("/'#[^']+':/", $element['original']['#plain_text'])
      && WebformYaml::isValid($element['original']['#plain_text'])) {
      $element['original'] = [
        '#theme' => 'webform_codemirror',
        '#code' => $element['original']['#plain_text'],
        '#type' => 'yaml',
      ];
      $element['translations'] = [
        '#type' => 'webform_message',
        '#message_type' => 'warning',
        '#message_message' => t("Webforms can only be translated via the Webform's (Configuration) Translate tab."),
      ];
    }
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function webform_form_config_translation_add_form_alter(&$form, FormStateInterface $form_state, $is_new = TRUE) {
  // Manually apply YAML editor to text field that store YAML data.
  foreach ($form['config_names'] as $config_name => &$config_element) {
    if ($config_name == 'webform.settings') {
      _webform_form_config_translate_add_form_alter_yaml_element($config_element['test']['types']);
      _webform_form_config_translate_add_form_alter_yaml_element($config_element['test']['names']);
    }
    elseif (strpos($config_name, 'webform.webform_options.') === 0) {
      _webform_form_config_translate_add_form_alter_yaml_element($config_element['options']);
    }
    elseif (strpos($config_name, 'webform.webform.') === 0) {
      $webform_id = str_replace('webform.webform.', '', $config_name);
      $webform = Webform::load($webform_id);

      /** @var \Drupal\webform\WebformTranslationManagerInterface $translation_manager */
      $translation_manager = \Drupal::service('webform.translation_manager');
      $translation_langcode = $form_state->get('config_translation_language')->getId();;
      $source_elements = $translation_manager->getSourceElements($webform);
      $translation_elements = $translation_manager->getTranslationElements($webform, $translation_langcode);
      $source_value = trim(Yaml::encode($source_elements));
      $translation_value = trim(Yaml::encode($translation_elements));

      _webform_form_config_translate_add_form_alter_yaml_element($config_element['elements'], $source_value, $translation_value);

      $config_element['elements']['translation']['#description'] = t('Please note: Custom properties will be automatically removed.');
      $form_state->set('webform_config_name', $config_name);
      $form_state->set('webform_source_elements', $source_elements);

      $form['#validate'][] = '_webform_form_config_translate_add_form_validate';

    }
  }
}

/**
 * Validate callback; Validates and cleanups webform elements.
 */
function _webform_form_config_translate_add_form_validate(&$form, FormStateInterface $form_state) {
  $values = $form_state->getValues();

  $config_name = $form_state->get('webform_config_name');
  $source_elements = $form_state->get('webform_source_elements');
  $submitted_translation_elements = Yaml::decode($values['translation']['config_names'][$config_name]['elements']);
  $translation_elements = $source_elements;

  // Remove all custom translation properties.
  WebformElementHelper::merge($translation_elements, $submitted_translation_elements);

  // Remove any translation property that has not been translated.
  _webform_form_config_translate_add_form_filter_elements($translation_elements, $source_elements);

  // Update webform value.
  $values['translation']['config_names'][$config_name]['elements'] = ($translation_elements) ? Yaml::encode($translation_elements) : '';
  $form_state->setValues($values);
}

/**
 * Merge element properties.
 *
 * @param array $translation_elements
 *   An array of elements.
 * @param array $source_elements
 *   An array of elements to be merged.
 */
function _webform_form_config_translate_add_form_filter_elements(array &$translation_elements, array $source_elements) {
  foreach ($translation_elements as $key => &$translation_element) {
    if (!isset($source_elements[$key])) {
      continue;
    }
    $source_element = $source_elements[$key];
    if ($translation_element == $source_element) {
      unset($translation_elements[$key]);
    }
    elseif (is_array($translation_element)) {
      _webform_form_config_translate_add_form_filter_elements($translation_element, $source_element);
      if (empty($translation_element)) {
        unset($translation_elements[$key]);
      }
    }
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function webform_form_config_translation_edit_form_alter(&$form, FormStateInterface $form_state) {
  webform_form_config_translation_add_form_alter($form, $form_state, FALSE);
}

/**
 * Alter translated config entity property.
 *
 * @param array $element
 *   A webform element containing 'source' and 'translation'.
 * @param string $source_value
 *   (optional) The custom config source value.
 * @param string $translation_value
 *   (optional) The custom config translation value.
 */
function _webform_form_config_translate_add_form_alter_yaml_element(array &$element, $source_value = NULL, $translation_value = NULL) {
  // Source.
  $element['source']['#wrapper_attributes']['class'][] = 'webform-translation-source';
  $element['source']['value'] = [
    '#type' => 'webform_codemirror',
    '#mode' => 'yaml',
    '#value' => WebformYaml::tidy($source_value ?: trim(strip_tags($element['source']['#markup']))),
    '#disabled' => TRUE,
    '#attributes' => ['readonly' => TRUE],
  ];
  unset($element['source']['#markup']);

  // Translation.
  $element['translation']['#type'] = 'webform_codemirror';
  $element['translation']['#mode'] = 'yaml';
  if ($translation_value) {
    $element['translation']['#default_value'] = WebformYaml::tidy($translation_value);
  }
  $element['translation']['#default_value'] = trim($element['translation']['#default_value']);
  $element['#attached']['library'][] = 'webform/webform.admin.translation';
}

/******************************************************************************/
// Lingotek integration.
/******************************************************************************/

/**
 * Implements hook_lingotek_config_entity_document_upload().
 */
function webform_lingotek_config_entity_document_upload(array &$source_data, ConfigEntityInterface &$entity, &$url) {
  switch ($entity->getEntityTypeId()) {
    case 'webform';
      /** @var \Drupal\webform\WebformTranslationManagerInterface $translation_manager */
      $translation_manager = \Drupal::service('webform.translation_manager');

      // Replace elements with just the translatable properties
      // (i.e. #title, #description, #options, etc???) so that Lingotek's
      // translation services can correctly translate each element.
      $translation_elements = $translation_manager->getTranslationElements($entity, $entity->language()->getId());
      $source_data['elements'] = $translation_elements;

      // Encode all [tokens].
      $yaml = Yaml::encode($source_data);
      $yaml = preg_replace_callback(
        '/\[([a-z][^]]+)\]/',
        function ($matches) {
          return '[***' . base64_encode($matches[1]) . '***]';
        },
        $yaml
      );
      $source_data = Yaml::decode($yaml);
      break;

    case 'webform_options';
      // Convert options YAML string to an associative array.
      $source_data['options'] = Yaml::decode($source_data['options']);
      break;
  }
}

/**
 * Implements hook_lingotek_config_entity_translation_presave().
 */
function webform_lingotek_config_entity_translation_presave(ConfigEntityInterface &$translation, $langcode, &$data) {
  switch ($translation->getEntityTypeId()) {
    case 'webform';
      // Decode all [tokens].
      $yaml = Yaml::encode($data);
      $yaml = preg_replace_callback(
        '/\[\*\*\*([^]]+)\*\*\*\]/',
        function ($matches) {
          return '[' . base64_decode($matches[1]) . ']';
        },
        $yaml
      );
      $data = Yaml::decode($yaml);

      /** @var \Drupal\webform\WebformInterface $translation */
      $translation->setElements($data['elements']);
      $data['elements'] = Yaml::encode($data['elements']);
      break;

    case 'webform_options';
      /** @var \Drupal\webform\WebformOptionsInterface $translation */
      // Convert options associative array back to YAML string.
      $translation->setOptions($data['options']);
      $data['options'] = Yaml::encode($data['options']);
      break;
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function webform_form_lingotek_config_management_alter(&$form, FormStateInterface $form_state, $form_id) {
  $entity_type = $form['filters']['wrapper']['bundle']['#default_value'];
  if (!in_array($entity_type, ['webform', 'webform_options'])) {
    return;
  }

  $entities = \Drupal::entityTypeManager()->getStorage($entity_type)->loadMultiple(array_keys($form['table']['#options']));
  foreach ($form['table']['#options'] as $entity_id => &$option) {
    $entity = $entities[$entity_id];
    $option['title'] = $entity->toLink($entity->label() . ' ' . $entity_type);
  }
}
