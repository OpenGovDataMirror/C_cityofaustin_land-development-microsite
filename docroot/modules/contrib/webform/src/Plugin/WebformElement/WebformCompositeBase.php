<?php

namespace Drupal\webform\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element as RenderElement;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\webform\Entity\WebformOptions;
use Drupal\webform\Plugin\WebformElementEntityReferenceInterface;
use Drupal\webform\Utility\WebformElementHelper;
use Drupal\webform\Utility\WebformOptionsHelper;
use Drupal\webform\Plugin\WebformElementBase;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides a base for composite elements.
 */
abstract class WebformCompositeBase extends WebformElementBase {

  /**
   * Composite elements defined in the webform composite form element.
   *
   * @var array
   *
   * @see \Drupal\webform\Element\WebformCompositeBase::processWebformComposite
   */
  protected $compositeElement;

  /**
   * Initialized composite element.
   *
   * @var array
   *
   * @see \Drupal\webform\Element\WebformCompositeBase::processWebformComputed
   */
  protected $initializedCompositeElement;

  /****************************************************************************/
  // Property methods.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function getDefaultProperties() {
    $properties = [
      'default_value' => [],
      'title_display' => 'invisible',
      'disabled' => FALSE,
      'flexbox' => '',
    ] + parent::getDefaultProperties() + $this->getDefaultMultipleProperties();
    unset($properties['required_error']);

    $composite_elements = $this->getCompositeElements();
    foreach ($composite_elements as $composite_key => $composite_element) {
      // Get #type, #title, and #option from composite elements.
      foreach ($composite_element as $composite_property_key => $composite_property_value) {
        if (in_array($composite_property_key, ['#type', '#title', '#options'])) {
          $property_key = str_replace('#', $composite_key . '__', $composite_property_key);
          if ($composite_property_value instanceof TranslatableMarkup) {
            $properties[$property_key] = (string) $composite_property_value;
          }
          else {
            $properties[$property_key] = $composite_property_value;
          }
        }
      }
      if (isset($properties[$composite_key . '__type'])) {
        $properties[$composite_key . '__title_display'] = '';
        $properties[$composite_key . '__description'] = '';
        $properties[$composite_key . '__help'] = '';
        $properties[$composite_key . '__required'] = FALSE;
        $properties[$composite_key . '__placeholder'] = '';
      }
      $properties[$composite_key . '__access'] = TRUE;
    }
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultMultipleProperties() {
    return [
      'multiple__header' => FALSE,
      'multiple__header_label' => '',
    ] + parent::getDefaultMultipleProperties();
  }

  /****************************************************************************/
  // Element relationship methods.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function getRelatedTypes(array $element) {
    return [];
  }

  /****************************************************************************/
  // Element rendering methods.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function initialize(array &$element) {
    parent::initialize($element);

    $this->initializeCompositeElements($element);
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, WebformSubmissionInterface $webform_submission = NULL) {
    parent::prepare($element, $webform_submission);

    // If #flexbox is not set or an empty string, determine if the
    // webform is using a flexbox layout.
    if ((!isset($element['#flexbox']) || $element['#flexbox'] === '') && $webform_submission) {
      $webform = $webform_submission->getWebform();
      $element['#flexbox'] = $webform->hasFlexboxLayout();
    }
  }

  /**
   * Set multiple element wrapper.
   *
   * @param array $element
   *   An element.
   */
  protected function prepareMultipleWrapper(array &$element) {
    if (empty($element['#multiple']) || !$this->supportsMultipleValues()) {
      return;
    }

    parent::prepareMultipleWrapper($element);

    // Set #header.
    if (!empty($element['#multiple__header'])) {
      $element['#header'] = TRUE;

      // Set #element.
      // We don't need to get the initialized composite elements because
      // they will be initialized, prepared, and finalize by the
      // WebformMultiple (wrapper) element.
      // @see \Drupal\webform\Element\WebformMultiple::processWebformMultiple
      $element['#element'] = [];
      $composite_elements = $this->getCompositeElements();
      foreach (Element::children($composite_elements) as $composite_key) {
        $composite_element = $composite_elements[$composite_key];
        // Transfer '#{composite_key}_{property}' from main element to composite
        // element.
        foreach ($element as $property_key => $property_value) {
          if (strpos($property_key, '#' . $composite_key . '__') === 0) {
            $composite_property_key = str_replace('#' . $composite_key . '__', '#', $property_key);
            $composite_element[$composite_property_key] = $property_value;
          }
        }

        $element['#element'][$composite_key] = $composite_element;
      }
    }
  }

  /****************************************************************************/
  // Table methods.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function getTableColumn(array $element) {
    $key = $element['#webform_key'];
    $title = $element['#title'] ?: $key;
    $is_title_displayed = WebformElementHelper::isTitleDisplayed($element);

    // Get the main composite element, which can't be sorted.
    $columns = parent::getTableColumn($element);
    $columns['element__' . $key]['sort'] = FALSE;

    // Get individual composite elements.
    if (!$this->hasMultipleValues($element)) {
      $composite_elements = $this->getInitializedCompositeElement($element);
      foreach (RenderElement::children($composite_elements) as $composite_key) {
        $composite_element = $composite_elements[$composite_key];
        // Make sure the composite element is visible.
        $access_key = '#' . $composite_key . '__access';
        if (isset($element[$access_key]) && $element[$access_key] === FALSE) {
          continue;
        }

        // Add reference to initialized composite element so that it can be
        // used by ::formatTableColumn().
        $columns['element__' . $key . '__' . $composite_key] = [
          'title' => ($is_title_displayed ? $title . ': ' : '') . (!empty($composite_element['#title']) ? $composite_element['#title'] : $composite_key),
          'sort' => TRUE,
          'default' => FALSE,
          'key' => $key,
          'element' => $element,
          'property_name' => $composite_key,
          'composite_key' => $composite_key,
          'composite_element' => $composite_element,
          'plugin' => $this,
        ];
      }
    }

    return $columns;
  }

  /**
   * {@inheritdoc}
   */
  public function formatTableColumn(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    if (isset($options['composite_key']) && isset($options['composite_element'])) {
      $composite_element = $options['composite_element'];
      $composite_element['#webform_key'] = $element['#webform_key'];
      return $this->elementManager->invokeMethod('formatHtml', $composite_element, $webform_submission, $options);
    }
    else {
      return $this->formatHtml($element, $webform_submission);
    }
  }

  /****************************************************************************/
  // #states API methods.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function getElementSelectorOptions(array $element) {
    if ($this->hasMultipleValues($element)) {
      return [];
    }

    $title = $this->getAdminLabel($element) . ' [' . $this->getPluginLabel() . ']';
    $name = $element['#webform_key'];

    $selectors = [];
    $composite_elements = $this->getInitializedCompositeElement($element);
    foreach ($composite_elements as $composite_key => $composite_element) {
      $has_access = (!isset($composite_elements['#access']) || $composite_elements['#access']);
      if ($has_access && isset($composite_element['#type'])) {
        $element_plugin = $this->elementManager->getElementInstance($composite_element);
        $composite_title = (isset($composite_element['#title'])) ? $composite_element['#title'] : $composite_key;

        switch ($composite_element['#type']) {
          case 'label':
          case 'webform_message':
            break;

          case 'webform_select_other':
            $selectors[":input[name=\"{$name}[{$composite_key}][select]\"]"] = $composite_title . ' [' . $this->t('Select') . ']';
            $selectors[":input[name=\"{$name}[{$composite_key}][other]\"]"] = $composite_title . ' [' . $this->t('Textfield') . ']';
            break;

          default:
            $selectors[":input[name=\"{$name}[{$composite_key}]\"]"] = $composite_title . ' [' . $element_plugin->getPluginLabel() . ']';
            break;
        }
      }
    }
    return [$title => $selectors];
  }

  /****************************************************************************/
  // Display submission value methods.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function formatHtml(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    if (isset($options['composite_key'])) {
      return $this->formatCompositeHtml($element, $webform_submission, $options);
    }
    else {
      return parent::formatHtml($element, $webform_submission, $options);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function formatText(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    if (isset($options['composite_key'])) {
      return $this->formatCompositeText($element, $webform_submission, $options);
    }
    else {
      return parent::formatText($element, $webform_submission, $options);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function formatCustomItem($type, array &$element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $name = strtolower($type);

    // Parse element.composite_key from template and add composite element
    // to context.
    $template = trim($element['#format_' . $name]);
    if (preg_match_all("/element(?:\[['\"]|\.)([a-zA-Z0-9-_:]+)/", $template, $matches)) {
      $composite_elements = $this->getInitializedCompositeElement($element);
      $composite_keys = array_unique($matches[1]);

      $item_function = 'format' . $type;
      $options['context'] = [
        'element' => [],
      ];
      foreach ($composite_keys as $composite_key) {
        if (isset($composite_elements[$composite_key])) {
          $options['context']['element'][$composite_key] = $this->$item_function(['#format' => NULL] + $element, $webform_submission, ['composite_key' => $composite_key] + $options);
        }
      }
    }

    return parent::formatCustomItem($type, $element, $webform_submission, $options);
  }

  /**
   * {@inheritdoc}
   */
  protected function formatHtmlItem(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    if (!$this->hasValue($element, $webform_submission, $options)) {
      return '';
    }

    $format = $this->getItemFormat($element);
    switch ($format) {
      case 'list':
      case 'raw':
        $items = $this->formatCompositeHtmlItems($element, $webform_submission, $options);
        return [
          '#theme' => 'item_list',
          '#items' => $items,
        ];

      default:
        $lines = $this->formatHtmlItemValue($element, $webform_submission, $options);
        foreach ($lines as $key => $line) {
          if (is_string($line)) {
            $lines[$key] = ['#markup' => $line];
          }
          $lines[$key]['#suffix'] = '<br />';
        }
        // Remove the <br/> suffix from the last line.
        unset($lines[$key]['#suffix']);
        return $lines;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function formatHtmlItems(array &$element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $format = $this->getItemsFormat($element);
    if ($format !== 'table') {
      return parent::formatHtmlItems($element, $webform_submission, $options);
    }

    $composite_elements = $this->getInitializedCompositeElement($element);

    // Get header.
    $header = [];
    foreach (RenderElement::children($composite_elements) as $composite_key) {
      if (isset($composite_elements[$composite_key]['#access']) && $composite_elements[$composite_key]['#access'] === FALSE) {
        unset($composite_elements[$composite_key]);
        continue;
      }

      $composite_element = $composite_elements[$composite_key];
      $header[$composite_key] = [
        'data' => (isset($composite_element['#title'])) ? $composite_element['#title'] : $composite_key,
        'bgcolor' => '#eee',
      ];
    }

    // Get rows.
    $rows = [];
    $values = $this->getValue($element, $webform_submission, $options);
    foreach ($values as $delta => $value) {
      foreach ($header as $composite_key => $composite_title) {
        $composite_value = $this->formatCompositeHtml($element, $webform_submission, ['delta' => $delta, 'composite_key' => $composite_key] + $options);
        if (is_array($composite_value)) {
          $rows[$delta][$composite_key] = ['data' => $composite_value];
        }
        else {
          $rows[$delta][$composite_key] = ['data' => ['#markup' => $composite_value]];
        }
      }
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => [
        'width' => '100%',
        'cellspacing' => 0,
        'cellpadding' => 5,
        'border' => 1,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function formatTextItems(array &$element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $format = $this->getItemsFormat($element);
    if ($format === 'table') {
       $element['#format_items'] = 'hr';
    }
    return parent::formatTextItems($element, $webform_submission, $options);
  }

  /**
   * {@inheritdoc}
   */
  protected function formatTextItem(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    if (!$this->hasValue($element, $webform_submission, $options)) {
      return '';
    }

    $format = $this->getItemFormat($element);
    switch ($format) {
      case 'list':
      case 'raw':
        $lines = $this->formatCompositeTextItems($element, $webform_submission, $options);
        return implode(PHP_EOL, $lines);

      default:
        $lines = $this->formatTextItemValue($element, $webform_submission, $options);
        return implode(PHP_EOL, $lines);
    }
  }

  /**
   * Format a composite as a list of HTML items.
   *
   * @param array $element
   *   An element.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission.
   * @param array $options
   *   An array of options.
   *
   * @return array|string
   *   A composite as a list of HTML items.
   */
  protected function formatCompositeHtmlItems(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $format = $this->getItemFormat($element);
    $items = [];
    $composite_elements = $this->getInitializedCompositeElement($element);
    foreach (RenderElement::children($composite_elements) as $composite_key) {
      $composite_element = $composite_elements[$composite_key];
      $composite_title = (isset($composite_element['#title']) && $format != 'raw') ? $composite_element['#title'] : $composite_key;
      $composite_value = $this->formatCompositeHtml($element, $webform_submission, ['composite_key' => $composite_key] + $options);
      if ($composite_value !== '') {
        $items[$composite_key] = [
          '#type' => 'inline_template',
          '#template' => '<b>{{ title }}:</b> {{ value }}',
          '#context' => [
            'title' => $composite_title,
            'value' => $composite_value,
          ],
        ];
      }
    }
    return $items;
  }

  /**
   * Format a composite as a list of plain text items.
   *
   * @param array $element
   *   An element.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission.
   * @param array $options
   *   An array of options.
   *
   * @return array|string
   *   A composite as a list of plain text items.
   */
  protected function formatCompositeTextItems(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $format = $this->getItemFormat($element);
    $items = [];
    $composite_elements = $this->getInitializedCompositeElement($element);
    foreach (RenderElement::children($composite_elements) as $composite_key) {
      $composite_element = $composite_elements[$composite_key];

      $composite_title = (isset($composite_element['#title']) && $format != 'raw') ? $composite_element['#title'] : $composite_key;

      $composite_value = $this->formatCompositeText($element, $webform_submission, ['composite_key' => $composite_key] + $options);
      if (is_array($composite_value)) {
        $composite_value = \Drupal::service('renderer')->renderPlain($composite_value);
      }

      if ($composite_value !== '') {
        $items[$composite_key] = "$composite_title: $composite_value";
      }
    }
    return $items;
  }

  /**
   * Format a composite's sub element's value as HTML.
   *
   * @param array $element
   *   An element.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission.
   * @param array $options
   *   An array of options.
   *
   * @return array|string
   *   A composite's sub element's value formatted as an HTML string or a render array.
   */
  protected function formatCompositeHtml(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    return $this->formatComposite('Html', $element, $webform_submission, $options);
  }

  /**
   * Format a composite's sub element's value as plain text.
   *
   * @param array $element
   *   An element.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission.
   * @param array $options
   *   An array of options.
   *
   * @return array|string
   *   A composite's sub element's value formatted as plain text or a render array.
   */
  protected function formatCompositeText(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    return $this->formatComposite('Text', $element, $webform_submission, $options);
  }

  /**
   * Format a composite's sub element's value as HTML or plain text.
   *
   * @param string $type
   *   The format type, HTML or Text.
   * @param array $element
   *   An element.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission.
   * @param array $options
   *   An array of options.
   *
   * @return array|string
   *   The element's value formatted as plain text or a render array.
   */
  protected function formatComposite($type, array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $options['webform_key'] = $element['#webform_key'];
    $composite_element = $this->getInitializedCompositeElement($element, $options['composite_key']);
    $composite_plugin = $this->elementManager->getElementInstance($composite_element);
    $format_function = 'format' . $type;
    return $composite_plugin->$format_function($composite_element, $webform_submission, $options);
  }

  /**
   * Format composite element value into lines of text.
   *
   * @param array $element
   *   An element.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission.
   * @param array $options
   *   An array of options.
   *
   * @return array
   *   Composite element values converted into lines of html.
   */
  protected function formatHtmlItemValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    return $this->formatCompositeHtmlItems($element, $webform_submission, $options);
  }

  /**
   * Format composite element value into lines of text.
   *
   * @param array $element
   *   An element.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission.
   * @param array $options
   *   An array of options.
   *
   * @return array
   *   Composite element values converted into lines of text.
   */
  protected function formatTextItemValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    return $this->formatCompositeTextItems($element, $webform_submission, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getItemFormats() {
    return parent::getItemFormats() + [
        'list' => $this->t('List'),
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function getItemsDefaultFormat() {
    return 'ul';
  }

  /**
   * {@inheritdoc}
   */
  public function getItemsFormats() {
    return [
      'ol' => $this->t('Ordered list'),
      'ul' => $this->t('Unordered list'),
      'hr' => $this->t('Horizontal rule'),
      'table' => $this->t('Table'),
    ];
  }

  /****************************************************************************/
  // Export methods.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function getExportDefaultOptions() {
    return [
      'composite_element_item_format' => 'label',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildExportOptionsForm(array &$form, FormStateInterface $form_state, array $export_options) {
    parent::buildExportOptionsForm($form, $form_state, $export_options);
    if (isset($form['composite'])) {
      return;
    }

    $form['composite'] = [
      '#type' => 'details',
      '#title' => $this->t('Composite element options'),
      '#open' => TRUE,
      '#weight' => -10,
    ];
    $form['composite']['composite_element_item_format'] = [
      '#type' => 'radios',
      '#title' => $this->t('Composite element item format'),
      '#options' => [
        'label' => $this->t('Option labels, the human-readable value (label)'),
        'key' => $this->t('Option values, the raw value stored in the database (key)'),
      ],
      '#default_value' => $export_options['composite_element_item_format'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildExportHeader(array $element, array $options) {
    if ($this->hasMultipleValues($element)) {
      return parent::buildExportHeader($element, $options);
    }

    $composite_elements = $this->getInitializedCompositeElement($element);
    $header = [];
    foreach (RenderElement::children($composite_elements) as $composite_key) {
      $composite_element = $composite_elements[$composite_key];
      if (isset($composite_element['#access']) && $composite_element['#access'] === FALSE) {
        continue;
      }

      if ($options['header_format'] == 'label' && !empty($composite_element['#title'])) {
        $header[] = $composite_element['#title'];
      }
      else {
        $header[] = $composite_key;
      }
    }

    return $this->prefixExportHeader($header, $element, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function buildExportRecord(array $element, WebformSubmissionInterface $webform_submission, array $export_options) {
    $value = $this->getValue($element, $webform_submission);

    if ($this->hasMultipleValues($element)) {
      $element['#format'] = ($export_options['header_format'] == 'label') ? 'list' : 'raw';
      $export_options['multiple_delimiter'] = PHP_EOL . '---' . PHP_EOL;
      return parent::buildExportRecord($element, $webform_submission, $export_options);
    }

    $record = [];
    $composite_elements = $this->getInitializedCompositeElement($element);
    foreach (RenderElement::children($composite_elements) as $composite_key) {
      $composite_element = $composite_elements[$composite_key];
      if (isset($composite_element['#access']) && $composite_element['#access'] === FALSE) {
        continue;
      }

      if ($export_options['composite_element_item_format'] == 'label' && $composite_element['#type'] != 'textfield' && !empty($composite_element['#options'])) {
        $record[] = WebformOptionsHelper::getOptionText($value[$composite_key], $composite_element['#options']);
      }
      else {
        $record[] = (isset($value[$composite_key])) ? $value[$composite_key] : NULL;
      }
    }
    return $record;
  }

  /****************************************************************************/
  // Test methods.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function getTestValues(array $element, WebformInterface $webform, array $options = []) {
    if (empty($element['#webform_composite_elements'])) {
      $this->initialize($element);
    }

    /** @var \Drupal\webform\WebformSubmissionGenerateInterface $generate */
    $generate = \Drupal::service('webform_submission.generate');

    $composite_elements = $this->getInitializedCompositeElement($element);
    $composite_elements = WebformElementHelper::getFlattened($composite_elements);

    $values = [];
    for ($i = 1; $i <= 3; $i++) {
      $value = [];
      foreach (RenderElement::children($composite_elements) as $composite_key) {
        $value[$composite_key] = $generate->getTestValue($webform, $composite_key, $composite_elements[$composite_key], $options);
      }
      $values[] = $value;
    }
    return $values;
  }

  /****************************************************************************/
  // Element configuration methods.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['custom']['properties']['#description'] .= '<br /><br />' .
      $this->t("You can set sub-element properties using a double underscore between the sub-element's key and sub-element's property (subelement__property). For example, you can add custom attributes or states (conditional logic) to the title sub-element using 'title__attributes' and 'title__states'.");
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // Update #default_value description.
    $form['default']['default_value']['#description']['content']['#markup'] = $this->t("The default value of the composite webform element as YAML.");

    // Update #required label.
    $form['validation']['required_container']['required']['#title'] .= ' <em>' . $this->t('(Display purposes only)') . '</em>';
    $form['validation']['required_container']['required']['#description'] = $this->t('If checked, adds required indicator to the title, if visible. To enforce individual fields, also tick "Required" under the @name settings above.', ['@name' => $this->getPluginLabel()]);

    // Update '#multiple__header_label'.
    $form['element']['multiple__header_container']['multiple__header_label']['#states']['visible'][':input[name="properties[multiple__header]"]'] = ['checked' => FALSE];

    $form['composite'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('@title settings', ['@title' => $this->getPluginLabel()]),
      '#attributes' => ['class' => ['webform-admin-composite-elements']],
    ];
    $form['composite']['element'] = $this->buildCompositeElementsTable();
    $form['composite']['flexbox'] = [
      '#type' => 'select',
      '#title' => $this->t('Use Flexbox'),
      '#description' => $this->t("If 'Automatic' is selected Flexbox layout will only be used if a 'Flexbox layout' element is included in the webform."),
      '#options' => [
        '' => $this->t('Automatic'),
        0 => $this->t('No'),
        1 => $this->t('Yes'),
      ],
    ];

    $item_pattern = &$form['display']['item']['patterns']['#value']['items']['#items'];
    $composite_elements = $this->getCompositeElements();
    foreach ($composite_elements as $composite_key => $composite_element) {
      $item_pattern[] = "{{ element.$composite_key }}";
    }

    // Hide single item display when multiple item display is set to 'table'.
    $form['display']['item']['#states']['invisible'] = [
      ':input[name="properties[format_items]"]' => ['value' => 'table'],
    ];

    $form['#attached']['library'][] = 'webform/webform.admin.composite';

    return $form;
  }

  /**
   * Build the composite elements settings table.
   *
   * @return array
   *   A renderable array container the composite elements settings table.
   */
  protected function buildCompositeElementsTable() {
    $labels_help = [
      'help' => [
        '#type' => 'webform_help',
        '#help' => '<b>' . t('Key') . ':</b> ' . t('The machine-readable name.') .
          '<b>' . t('Title') . ':</b> ' . t('This is used as a descriptive label when displaying this webform element.') . '<br/><br/>' .
          '<b>' . t('Placeholder') . ':</b> ' . t('The placeholder will be shown in the element until the user starts entering a value.') . '<br/><br/>' .
          '<b>' . t('Description') . ':</b> ' . t('A short description of the element used as help for the user when he/she uses the webform.') . '<br/><br/>' .
          '<b>' . t('Help text') . ':</b> ' . t('A tooltip displayed after the title.') . '<br/><br/>' .
          '<b>' . t('Title display') . ':</b> ' . t('A tooltip displayed after the title.'),
        '#help_title' => $this->t('Labels'),
      ],
    ];
    $settings_help = [
      'help' => [
        '#type' => 'webform_help',
        '#help' => '<b>' . t('Required') . ':</b> ' . t('Check this option if the user must enter a value.') . '<br/><br/>' .
          '<b>' . t('Type') . ':</b> ' . t('The type of element to be displayed.') . '<br/><br/>' .
          '<b>' . t('Options') . ':</b> ' . t('Please select predefined options.'),
        '#help_title' => $this->t('Settings'),
      ],
    ];

    $header = [
      'visible' => $this->t('Visible'),
      'labels' => [
        'data' => [
          ['title' => ['#markup' => $this->t('Labels')]],
          $labels_help,
        ],
      ],
      'settings' => [
        'data' => [
          ['title' => ['#markup' => $this->t('Settings')]],
          $settings_help,
        ],
      ],
    ];

    $rows = [];
    $composite_elements = $this->getCompositeElements();
    foreach ($composite_elements as $composite_key => $composite_element) {
      $title = (isset($composite_element['#title'])) ? $composite_element['#title'] : $composite_key;
      $type = isset($composite_element['#type']) ? $composite_element['#type'] : NULL;
      $t_args = ['@title' => $title];
      $state_disabled = [
        'disabled' => [
          ':input[name="properties[' . $composite_key . '__access]"]' => [
            'checked' => FALSE,
          ],
        ],
      ];

      $row = [];

      // Access.
      $row[$composite_key . '__access'] = [
        '#title' => $this->t('@title visible', $t_args),
        '#title_display' => 'invisible',
        '#type' => 'checkbox',
        '#return_value' => TRUE,
      ];

      // Key, title, placeholder, help, description, and title display.
      if ($type) {
        $row['labels'] = [
          'data' => [
            $composite_key . '__key' => [
              '#markup' => $composite_key,
              '#suffix' => '<hr/>',
              '#access' => TRUE,
            ],
            $composite_key . '__title' => [
              '#type' => 'textfield',
              '#title' => $this->t('@title title', $t_args),
              '#title_display' => 'invisible',
              '#description' => t('This is used as a descriptive label when displaying this webform element.'),
              '#description_display' => 'invisible',
              '#placeholder' => $this->t('Enter title???'),
              '#required' => TRUE,
              '#states' => $state_disabled,
            ],
            $composite_key . '__placeholder' => [
              '#type' => 'textfield',
              '#title' => $this->t('@title placeholder', $t_args),
              '#title_display' => 'invisible',
              '#description' => t('The placeholder will be shown in the element until the user starts entering a value.'),
              '#description_display' => 'invisible',
              '#placeholder' => $this->t('Enter placeholder???'),
              '#states' => $state_disabled,
            ],
            $composite_key . '__help' => [
              '#type' => 'textarea',
              '#title' => $this->t('@title help text', $t_args),
              '#title_display' => 'invisible',
              '#description' => t('A short description of the element used as help for the user when he/she uses the webform.'),
              '#description_display' => 'invisible',
              '#rows' => 2,
              '#placeholder' => $this->t('Enter help text???'),
              '#states' => $state_disabled,
            ],
            $composite_key . '__description' => [
              '#type' => 'textarea',
              '#title' => $this->t('@title description', $t_args),
              '#title_display' => 'invisible',
              '#description' => t('A tooltip displayed after the title.'),
              '#description_display' => 'invisible',
              '#rows' => 2,
              '#placeholder' => $this->t('Enter description???'),
              '#states' => $state_disabled,
            ],
            $composite_key . '__title_display' => [
              '#type' => 'select',
              '#title' => $this->t('@title title display', $t_args),
              '#title_display' => 'invisible',
              '#description' => t('A tooltip displayed after the title.'),
              '#description_display' => 'invisible',
              '#options' => [
                'before' => $this->t('Before'),
                'after' => $this->t('After'),
                'inline' => $this->t('Inline'),
                'invisible' => $this->t('Invisible'),
              ],
              '#empty_option' => $this->t('Select title display??? '),
              '#states' => $state_disabled,
            ],
          ],
        ];
      }
      else {
        $row['title_and_description'] = ['data' => ['']];
      }

      // Type and options.
      // Using if/else instead of switch/case because of complex conditions.
      $row['settings'] = [];

      // Required.
      if ($type) {
        $row['settings']['data'][$composite_key . '__required'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Required'),
          '#description' => t('Check this option if the user must enter a value.'),
          '#description_display' => 'invisible',
          '#return_value' => TRUE,
          '#states' => $state_disabled,
          '#wrapper_attributes' => ['style' => 'white-space: nowrap'],
          '#suffix' => '<hr/>',
        ];
      }

      if ($type == 'tel') {
        $row['settings']['data'][$composite_key . '__type'] = [
          '#type' => 'select',
          '#title' => $this->t('@title type', $t_args),
          '#title_display' => 'invisible',
          '#description' => t('The type of element to be displayed.'),
          '#description_display' => 'invisible',
          '#required' => TRUE,
          '#options' => [
            'tel' => $this->t('Telephone'),
            'textfield' => $this->t('Text field'),
          ],
          '#states' => $state_disabled,
        ];
      }
      elseif (in_array($type, ['select', 'webform_select_other', 'radios', 'webform_radios_other'])) {
        // Get base type (select or radios).
        $base_type = preg_replace('/webform_(select|radios)_other/', '\1', $type);

        // Get type options.
        switch ($base_type) {
          case 'radios':
            $settings = [
              'radios' => $this->t('Radios'),
              'webform_radios_other' => $this->t('Radios other'),
              'textfield' => $this->t('Text field'),
            ];
            break;

          case 'select':
          default:
            $settings = [
              'select' => $this->t('Select'),
              'webform_select_other' => $this->t('Select other'),
              'textfield' => $this->t('Text field'),
            ];
            break;
        }

        $row['settings']['data'][$composite_key . '__type'] = [
          '#type' => 'select',
          '#title' => $this->t('@title type', $t_args),
          '#title_display' => 'invisible',
          '#description' => t('The type of element to be displayed.'),
          '#description_display' => 'invisible',
          '#required' => TRUE,
          '#options' => $settings,
          '#states' => $state_disabled,
        ];
        if ($composite_options = $this->getCompositeElementOptions($composite_key)) {
          $row['settings']['data'][$composite_key . '__options'] = [
            '#type' => 'select',
            '#title' => $this->t('@title options', $t_args),
            '#title_display' => 'invisible',
            '#description' => t('Please select predefined options.'),
            '#description_display' => 'invisible',
            '#options' => $composite_options,
            '#required' => TRUE,
            '#attributes' => ['style' => 'width: 100%;'],
            '#states' => $state_disabled + [
                'invisible' => [
                  ':input[name="properties[' . $composite_key . '__type]"]' => [
                    'value' => 'textfield',
                  ],
                ],
              ],
          ];
        }
        else {
          $row['settings']['data'][$composite_key . '__options'] = [
            '#type' => 'value',
          ];
        }
      }
      else {
        $row['settings']['data'][$composite_key . '__type'] = [
          '#type' => 'textfield',
          '#access' => FALSE,
        ];
        $row['settings']['data']['markup'] = [
          '#type' => 'container',
          '#markup' => $this->elementManager->getElementInstance($composite_element)->getPluginLabel(),
          '#access' => TRUE,
        ];
      }

      $rows[$composite_key] = $row;
    }

    return [
      '#type' => 'table',
      '#header' => $header,
    ] + $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationFormProperties(array &$form, FormStateInterface $form_state) {
    $properties = parent::getConfigurationFormProperties($form, $form_state);
    foreach ($properties as $key => $value) {
      // Convert composite element access and required to boolean value.
      if (preg_match('/__(access|required)$/', $key)) {
        $properties[$key] = (boolean) $value;
      }
    }
    return $properties;
  }

  /****************************************************************************/
  // Composite element methods.
  /****************************************************************************/

  /**
   * Initialize and cache #webform_composite_elements.
   *
   * @param array $element
   *   A composite element.
   */
  public function initializeCompositeElements(array &$element) {
    // @see \Drupal\webform\Plugin\WebformElement\WebformCompositeBase::getInitializedCompositeElement
    $class = $this->getFormElementClassDefinition();
    $element['#webform_composite_elements'] = $class::initializeCompositeElements($element);
  }

  /**
   * Get composite element.
   *
   * @return array
   *   An array of composite sub-elements or a specific composite sub element key.
   */
  public function getCompositeElements() {
    $class = $this->getFormElementClassDefinition();
    return $class::getCompositeElements([]);
  }

  /**
   * Get initialized composite element.
   *
   * @param array $element
   *   A composite element.
   * @param string $composite_key
   *   (Optional) Composite sub element key.
   *
   * @return array
   *   The initialized composite element or a specific composite sub element key.
   *
   * @see \Drupal\webform\Plugin\WebformElement\WebformCompositeBase::initialize
   */
  public function getInitializedCompositeElement(array $element, $composite_key = NULL) {
    $composite_elements = $element['#webform_composite_elements'];
    if (isset($composite_key)) {
      return (isset($composite_elements[$composite_key])) ? $composite_elements[$composite_key] : NULL;
    }
    else {
      return $composite_elements;
    }
  }

  /**
   * Get webform option keys for composite element based on the composite element's key.
   *
   * @param string $composite_key
   *   A composite element's key.
   *
   * @return array
   *   An array webform options.
   */
  protected function getCompositeElementOptions($composite_key) {
    /** @var \Drupal\webform\WebformOptionsInterface[] $webform_options */
    $webform_options = WebformOptions::loadMultiple();
    $options = [];
    foreach ($webform_options as $key => $webform_option) {
      if (strpos($key, $composite_key) === 0) {
        $options[$key] = $webform_option->label();
      }
    }
    return $options;
  }

  /****************************************************************************/
  // Composite helper methods.
  /****************************************************************************/

  /**
   * Determine if element type is supported by custom composite elements.
   *
   * @param string $type
   *   An element type.
   *
   * @return bool
   *   TRUE if element type is supported by custom composite elements.
   */
  public static function isSupportedElementType($type) {
    /** @var \Drupal\webform\Plugin\WebformElementManagerInterface $element_manager */
    $element_manager = \Drupal::service('plugin.manager.webform.element');

    // Skip element types that are not supported.
    $element = ['#type' => $type];
    $element_plugin = $element_manager->getElementInstance($element);
    if (!$element_plugin->isInput($element)
      || $element_plugin->isComposite()
      || $element_plugin->isContainer($element)
      || $element_plugin->hasMultipleValues($element)
      || $element_plugin instanceof WebformElementEntityReferenceInterface
      || $element_plugin instanceof WebformComputedBase
      || $element_plugin instanceof WebformManagedFileBase) {
      return FALSE;
    }

    // Skip ignored types that are not supported.
    $ignored_element_types = [
      'hidden',
      'value',
      'webform_autocomplete',
      'webform_image_select',
      'webform_terms_of_service',
    ];
    if (in_array($type, $ignored_element_types)) {
      return FALSE;
    }

    return TRUE;
  }

}
