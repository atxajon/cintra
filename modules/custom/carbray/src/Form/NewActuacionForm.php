<?php
/**
 * @file
 * Contains \Drupal\carbray\Form\NewExpedienteForm.
 */
namespace Drupal\carbray\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;


/**
 * NewActuacionForm form.
 */
class NewActuacionForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'carbray_new_actuacion';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $expediente_nid = 0) {
    // Disable caching on this form.
    $form_state->setCached(FALSE);

    $form['expediente_nid'] = array(
      '#type' => 'hidden',
      '#default_value' => $expediente_nid,
    );

    $form['title'] = array(
      '#title' => 'Actuacion',
      '#type' => 'textfield',
      '#required' => TRUE,
    );

    $form['#attached']['library'][] = 'carbray/carbray.carbray_timer';
    // Does this expediente have a pack de horas set? if so pass it to js timer file.
    $expediente = Node::load($expediente_nid);
    $pack = $expediente->get('field_expediente_pack_de_horas')->value;
    $form['#attached']['drupalSettings']['pack_horas'] = $pack;

    $form['is_pack'] = array(
      '#type' => 'hidden',
      '#default_value' => $pack,
    );

    $crono_time = ($pack) ? gmdate("H:i:s", $pack * 3600) : '00:00:00';

    $form['start'] = array(
      '#type' => 'button',
      '#value' => 'Comenzar',
      '#prefix' => '<div class="pull-left clearfix timer-container"><div class="pull-left crono-wrapper"><h2 id="crono" class="no-margin crono-heading pull-left">' . $crono_time . '</h2>',
      '#attributes' => array('class' => array('btn-primary', 'margin-bottom-20', 'start-timer-btn')),
    );

    $form['resume'] = array(
      '#type' => 'button',
      '#value' => 'Continuar',
      '#attributes' => array('class' => array('btn-primary', 'margin-bottom-20', 'resume-timer-btn', 'hidden')),
    );

    $form['pause'] = array(
      '#type' => 'button',
      '#value' => 'Pausar',
      '#attributes' => array('class' => array('hidden', 'pause-timer-btn', 'btn-warning')),
    );


    $form['timer'] = array(
      '#type' => 'textfield',
      '#description' => 'Edita el numero de minutos transcurridos.',
      '#required' => TRUE,
      '#prefix' => '<div class="pull-right timer-textfield">',
      '#attributes' => array('class' => array('hidden')),
      '#suffix' => '</div></div></div>',
    );

    $form['nota_container'] = array(
      '#type' => 'container',
      '#attributes' => array('class' => array('margin-bottom-20 margin-top-20')),
    );
    $form['nota_container']['nota'] = array(
      '#type' => 'text_format',
      '#title' => 'Notas de la actuacion',
      '#format' => 'basic_html',
      '#rows' => 5,
    );

    $allowed_exts = array('jpg jpeg gif png txt doc xls xlsx pdf ppt pptx pps odt ods odp docx zip rar msg');
    $form['actuacion_file'] = array(
      '#type' => 'managed_file',
      '#name' => 'my_file',
      '#title' => t('Añadir Documentacion'),
      '#size' => 20,
      '#description' => t('Allowed Files - jpg jpeg gif png txt doc xls xlsx pdf ppt pptx pps odt ods odp docx zip rar msg'),
      '#upload_validators' => array('file_validate_extensions' => $allowed_exts),
      '#upload_location' => 'private://actuacion/',
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => 'Añadir actuacion',
      '#attributes' => array('class' => array('btn-primary', 'create-actuacion')),
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

    $expediente_nid = $form_state->getValue('expediente_nid');
    $title = $form_state->getValue('title');
    $timer = $form_state->getValue('timer');
    $is_pack = $form_state->getValue('is_pack');
    $nota = $form_state->getValue('nota');
    $actuacion_file = $form_state->getValue('actuacion_file');

    $actuacion = Node::create(['type' => 'actuacion']);
    $actuacion->set('title', $title);
    $actuacion->set('field_actuacion_expediente', $expediente_nid);
    $actuacion->set('field_actuacion_tiempo_en_seg', $timer);
    $actuacion->set('field_actuacion_documentacion', $actuacion_file);
    $actuacion->enforceIsNew();
    $actuacion->save();

    $nota_node = Node::create(['type' => 'nota']);
    $nota_node->set('title', 'Nota para id ' . $actuacion->id() . ' creada el ' . date('d-M-Y H:m:s', time()));
    $nota_node->set('field_nota_nota', $nota);
    $nota_node->enforceIsNew();
    $nota_node->save();

    $actuacion = Node::load($actuacion->id());
    $actuacion->set('field_actuacion_nota', $nota_node->id());
    $actuacion->save();

    // If it's an expediente with pack de horas update remaining time.
    // @todo: test all this!
    if ($is_pack) {
      // When this actuacion started we had originally stored $actuacion_started_minutes.
      $actuacion_started_minutes = $is_pack * 60;
      // The absolute time passed on this current actuacion is:
      $minutes_passed_on_this_actuacion = $actuacion_started_minutes - $timer;

      $expediente = Node::load($expediente_nid);
      // Currently stored hours in pack (we need to check against this, as another worker could have done an actuacion in paralel while this worker submits his!
      $current_pack_hours = $expediente->get('field_expediente_pack_de_horas')->value;
      $current_pack_minutes = $current_pack_hours * 60;

      $updated_pack_remaining_minutes = $current_pack_minutes - $minutes_passed_on_this_actuacion;
      $updated_pack_remaining_hours = $updated_pack_remaining_minutes / 60;

      // Store the subtracted minutes in hours for the remaining time in the pack.
      $expediente->set('field_expediente_pack_de_horas', $updated_pack_remaining_hours);
      $expediente->save();
    }

    drupal_set_message('Actuacion ' . $title . ' ha sido creada');
  }
}
