<?php

namespace Drupal\lightgallery\Field;


use Drupal\lightgallery\Group\GroupLightgalleryCore;

class FieldImage extends FieldBase{

  /**
   * @Inherit doc.
   */
  public function appliesToFieldFormatter() {
    return FALSE;
  }

  /**
   * @Inherit doc.
   */
  protected function setIsRequired() {
    return TRUE;
  }

  /**
   * @Inherit doc.
   */
  protected function setDefaultValue() {
    return NULL;
  }

  /**
   * @Inherit doc.
   */
  protected function setOptions() {
    return ['Drupal\lightgallery\Plugin\views\style\LightGallery', 'getImageFields'];
  }

  /**
   * @Inherit doc.
   */
  protected function setName(){
    return 'image_field';
  }

  /**
   * @Inherit doc.
   */
  protected function setTitle(){
    return 'Image field';
  }

  /**
   * @Inherit doc.
   */
  protected function setType(){
    return FieldTypesEnum::SELECT;
  }

  /**
   * @Inherit doc.
   */
  protected function setDescription(){
    return 'Select the field you want to use to display the images in the Lightgallery.';
  }

  /**
   * @Inherit doc.
   */
  protected function setGroup(){
    return new GroupLightgalleryCore();
  }

}