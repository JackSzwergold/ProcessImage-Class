<?php

/**
 * ProcessImage Class (a PHP class to handle images via ImageMagic & GD) (c) by Jack Szwergold
 *
 * ProcessImage Class is licensed under a
 * Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International License.
 *
 * You should have received a copy of the license along with this
 * work. If not, see <http://creativecommons.org/licenses/by-nc-sa/4.0/>. 
 *
 * w: https://www.szwergold.com
 * e: jackszwergold@icloud.com
 *
 * Version: 2010-10-24
 *
 * Changes: 2008-10-12 js: last revisions with Scriptaculous/Prototype
 *          2010-10-24 js: cleanups and adjusted for jQuery
 *          2013-01-26 js: revisiting for new use.
 *          2015-04-16 js: revisiting again to see if I can get this to work.
 *
 */

//**************************************************************************************//
// Here is where the magic happens!

class ProcessImage  {

  // Set variable defaults.
  private $quality = 100;
  private $source = NULL;
  private $dest = NULL;
  private $width = NULL;
  private $height = NULL;
  private $gamma = 1.0;
  private $gravity = 'northwest';
  private $mode = 'imagemagick';
  # private $convert_path = '/usr/local/bin/convert';
  private $convert_path = '/opt/ImageMagick/bin/convert';

  // Set variables via the class.
  public function quality($value = NULL) { $this->quality = $value; }
  public function source($value = NULL) { $this->source = $value; }
  public function dest($value = NULL) { $this->dest = $value; }
  public function gamma($value = NULL) { $this->gamma = isset($value) ? $value : $this->gamma; }
  public function mode($value = NULL) { $this->mode = isset($value) ? $value : $this->mode; }
  public function gravity($value = NULL) { $this->gravity = isset($value) ? $value : $this->gravity; }

  public function scale ($width = NULL, $height = NULL) {
    if ($this->mode == 'gd')
      $this->scale_gd($width, $height);
    else
      $this->scale_imagemagick($width, $height);
  }

  public function crop ($width_s, $height_s, $crop_w, $crop_h, $x = 0, $y = 0) {
    if ($this->mode == 'gd')
      $this->crop_gd($width_s, $height_s, $crop_w, $crop_h, $x, $y);
    else
      $this->crop_imagemagick($width_s, $height_s, $crop_w, $crop_h, $x, $y);
  }

  //************************************************************************************************
  // Helper Functions ******************************************************************************
  //************************************************************************************************

  // Figure out what method to load the image in via GD.
  private function gd_imagecreate($source) {
    $info = @getimagesize($source);
    switch ($info['mime']) {
      case 'image/png':
        return @imagecreatefrompng($source);
        break;
      case 'image/jpeg':
        return @imagecreatefromjpeg($source);
        break;
      case 'image/gif':
        return @imagecreatefromgif($source);
        break;
      default:
        return FALSE;
        break;
    }
  }

  // Generate a JPEG preview in the browser window.
  public function show($file = NULL) {
    $file = (isset($file) && !empty($file)) ? $file : $this->dest;
    $img = $this->gd_imagecreate($file);
    $info = @getimagesize($file);
    // imagefilter($img, IMG_FILTER_GRAYSCALE);
    // imagefilter($img, IMG_FILTER_COLORIZE, 90, 60, 40);
    // imagefilter($img, IMG_FILTER_NEGATE);
    header('Content-Type: '.$info['mime']);
    switch ($info['mime']) {
     case 'image/png':
       imagepng($img, NULL, $this->quality);
       break;
     case 'image/jpeg':
       imagejpeg($img, NULL, $this->quality);
       break;
     case 'image/gif':
       imagegif($img, NULL, $this->quality);
       break;
     default:
       return FALSE;
       break;
    }
    imagedestroy($img);
  }

  // Calculate width/height via proportions for scaling.
  private function proportions ($width_s, $height_s, $width_d, $height_d) {
    $ret = array();
    if ($width_s < $height_s) {
      $ret['width'] = round(($width_s/$height_s)*$height_d);
      $ret['height'] = $height_d;
    }
    else if ($width_s > $height_s) {
      $ret['width'] = $width_d;
      $ret['height'] = round(($height_s/$width_s)*$width_d);
    }
    return $ret;
  }

  //************************************************************************************************
  // Scale Functions *******************************************************************************
  //************************************************************************************************

  // Use GD libraries to scale.
  private function scale_gd($width_d, $height_d) {
    $image_source = $this->gd_imagecreate($this->source);
    if ($image_source === FALSE)
      return FALSE;

    $width_o = imagesx($image_source);
    $height_o = imagesy($image_source);

    $new_size = $this->proportions($width_o, $height_o, $width_d, $height_d);

    $image_final = imagecreatetruecolor($new_size['width'], $new_size['height']);
    imagecopyresampled($image_final, $image_source, 0, 0, 0, 0, $new_size['width'], $new_size['height'], $width_o, $height_o );
    imagegammacorrect($image_final, 1.0, $this->gamma);
    imagejpeg($image_final, $this->dest, $this->quality);
  }

  // Use ImageMagick to scale.
  private function scale_imagemagick($width, $height) {
    $command = $this->convert_path
             . ' -strip -density 72 +profile "*" -colorspace RGB'
             . ' -quality ' . $this->quality
             . ' -geometry ' . escapeshellarg($width . 'x' . $height)
             . ' -gamma ' . $this->gamma
             . ' ' . escapeshellarg($this->source)
             . ' ' . escapeshellarg($this->dest)
             ;
    exec($command);
  }

  //************************************************************************************************
  // Crop Functions ********************************************************************************
  //************************************************************************************************

  // Use GD libraries to crop.
  private function crop_gd($width_s, $height_s, $crop_w, $crop_h, $x = 0, $y = 0) {
    $image_source = $this->gd_imagecreate($this->source);
    if ($image_source === FALSE)
      return FALSE;

    $width_o = imagesx($image_source);
    $height_o = imagesy($image_source);

    $new_size = $this->proportions($width_o, $height_o, $width_s, $height_s);

    $image_scaled = imagecreatetruecolor($new_size['width'], $new_size['height']);
    imagecopyresampled($image_scaled, $image_source, 0, 0, 0, 0, $new_size['width'], $new_size['height'], $width_o, $height_o );

    $image_final = imagecreatetruecolor($crop_w, $crop_h);
    imagecopyresampled($image_final, $image_scaled, 0, 0, $x, $y, $crop_w, $crop_h, $crop_w, $crop_h );
    imagegammacorrect($image_final, 1.0, $this->gamma);
    imagejpeg($image_final, $this->dest, $this->quality);
  }

  // Use ImageMagick to scale.
  private function crop_imagemagick($width_s, $height_s, $crop_w, $crop_h, $x = 0, $y = 0) {
    $command = $this->convert_path
             . ' -strip -density 72 +profile "*" -colorspace RGB'
             . ' -quality ' . $this->quality
             . ' -gamma ' . $this->gamma
             . ' -geometry ' . $width_s . 'x' . $height_s
             . ' -crop ' . escapeshellarg($crop_w . 'x' . $crop_h . '+' . abs($x) . '+' . abs($y))
             . ' ' . escapeshellarg($this->source)
             . ' ' . escapeshellarg($this->dest)
             ;
    exec($command);
  }

}

?>