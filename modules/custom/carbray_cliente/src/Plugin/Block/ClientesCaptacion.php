<?php

namespace Drupal\carbray_cliente\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\taxonomy\Entity\Term;


/**
 * Provides a 'ClientesCaptacion' block.
 *
 * @Block(
 *  id = "clientes_captacion",
 *  admin_label = @Translation("Clientes captacion"),
 * )
 */
class ClientesCaptacion extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $logged_in_uid = \Drupal::currentUser()->id();
    $clientes = get_my_clients($logged_in_uid);
    $rows = [];
    foreach ($clientes as $cliente) {
      $cliente_data = \Drupal::entityTypeManager()->getStorage('user')->load($cliente->uid);

      $captacion_data = \Drupal::entityTypeManager()->getStorage('node')->load($cliente->captacion_nid);

      $estado_nombre = '';
      $term_entity = $captacion_data->field_captacion_estado_captacion->entity;
      if ($term_entity) {
        $term = Term::load($term_entity->id());
        $estado_nombre = $term->name->value;
      }

      $rows[] = array(
        print_cliente_link($cliente_data),
        print_cliente_captadores_responsables($captacion_data->get('field_captacion_captador')->getValue()),
        $estado_nombre,
        ($cliente_data->getEmail()) ? $cliente_data->getEmail() : '',
        ($cliente_data->get('field_telefono')->value) ? $cliente_data->get('field_telefono')->value : '',
        print_captacion_link($cliente->captacion_nid),
      );
    }

    $header = array(
      'Nombre',
      'Captador',
      'Estado captacion',
      'Email',
      'Telefono',
      'Ver captacion',
    );
    $build = array(
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => t('Ningun cliente en captacion.'),
    );
    return $build;
  }
}
