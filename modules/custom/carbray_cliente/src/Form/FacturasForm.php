<?php
/**
 * @file
 * Contains \Drupal\carbray\Form\NewClientForm.
 */
namespace Drupal\carbray_cliente\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;


/**
 * ArchiveCaptacion form.
 */
class FacturasForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'facturas_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Adding checkboxes to a table using tableselect: https://www.drupal.org/node/945102

    $factura_ids = get_facturas();
    $options = [];
    foreach ($factura_ids as $factura_id) {
      $factura_node = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->load($factura_id);
      $factura_captacion = $factura_node->get('field_factura')->getValue();
      $captacion_node = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->load($factura_captacion[0]['target_id']);
      $captacion_uid = $captacion_node->get('field_captacion_cliente')
        ->getValue();
      $cliente_data = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->load($captacion_uid[0]['target_id']);

      $iva = ($factura_node->get('field_factura_iva')->value == 1) ? 'Con IVA' : 'Sin IVA';
      $options[$factura_id] = array(
        'cliente' => print_cliente_link($cliente_data, FALSE),
        'captador' => print_cliente_captadores_responsables($captacion_node->get('field_captacion_captador')
            ->getValue()),
        'nif' => $factura_node->get('field_factura_nif')->value,
        'iva' => $iva,
        'precio' => $factura_node->get('field_factura_precio')->value,
      );
    }

    $header = array(
      'cliente' => t('Cliente'),
      'captador' => t('Captador'),
      'nif' => t('NIF'),
      'iva' => t('IVA'),
      'precio' => t('Precio'),
    );

    $form['table'] = array(
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#js_select' => FALSE, // Don't want the select all checbox at the header.
      '#empty' => t('Ninguna factura sin pagar.'),
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Marcar facturas como pagadas'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Any unchecked items will be given a value of 0, checked items will be given a value of the item key.
    // We can use the array_filter function to give us only the selected items.
    $unpaid_factura_ids = array_filter($form_state->getValue('table'));
    foreach ($unpaid_factura_ids as $unpaid_factura_id) {
      // Update factura node field pagada to true.
      $factura_node = Node::load($unpaid_factura_id);
      $factura_node->set('field_factura_pagada', 1);
      $factura_node->save();
      
      // Notify abogados by email.
      $factura_captacion = $factura_node->get('field_factura')->getValue();
      $captacion_node = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->load($factura_captacion[0]['target_id']);
      $captacion_captadores = $captacion_node->get('field_captacion_captador')->getValue();
      foreach ($captacion_captadores as $captacion_captador) {
        $captador_user = User::load($captacion_captador['target_id']);
        $captador_email = $captador_user->getEmail();
        $to = $captador_email;
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'carbray';
        $langcode = \Drupal::currentUser()->getPreferredLangcode();
        $sent = $mailManager->mail($module, 'notify_captador_factura_paid', $to, $langcode);
        $mssg = ($sent) ? 'Email sent to abogado captador as a factura has been marked as paid by secretaria' : '';
        \Drupal::logger('carbray')->warning($mssg);
      }

    }
    drupal_set_message('Facturas marcadas como pagadas.');
  }
}