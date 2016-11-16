<?php
/**
 * fdvegan_blocks.php
 *
 * Implementation of all fdvegan Drupal Blocks' content.
 *
 * PHP version 5.6
 *
 * @category   Install
 * @package    fdvegan
 * @author     Rob Howe <rob@robhowe.com>
 * @copyright  2015-2016 Rob Howe
 * @license    This file is proprietary and subject to the terms defined in file LICENSE.txt
 * @version    Bitbucket via git: $Id$
 * @link       http://fivedegreevegan.aprojects.org
 * @since      version 0.5
 */


	 /**
     * Implements hook_block_info().
     */
    function fdvegan_block_info() {
        $blocks = array();
        $blocks['fdvegan_slider_block'] = array(
            'info'       => t('FDV Slider Block'),
            'cache'      => DRUPAL_CACHE_GLOBAL,
            'status'     => TRUE,  // enabled
            'region'     => 'content',  // see {theme}.info file
            'visibility' => BLOCK_VISIBILITY_LISTED,
            'pages'      => '<front>',
            'weight'     => 20,
        );
        $blocks['fdvegan_go_vegan_block'] = array(
            'info'       => t('FDV Go Vegan Block'),
            'cache'      => DRUPAL_CACHE_PER_ROLE,
            'status'     => TRUE,  // enabled
            'region'     => 'sidebar_first',
            'visibility' => BLOCK_VISIBILITY_NOTLISTED,
            'pages'      => '',
            'weight'     => 50,
        );
        $blocks['fdvegan_footer_block'] = array(
            'info'       => t('FDV Footer Block'),
            'cache'      => DRUPAL_CACHE_GLOBAL,
            'status'     => TRUE,  // enabled
            'region'     => 'footer',
            'visibility' => BLOCK_VISIBILITY_NOTLISTED,
            'pages'      => '',
            'weight'     => 20,
        );


        // other block-creation code snipped...


        return $blocks;
    }


    /**
     * Implements hook_block_view().
     */
    function fdvegan_block_view($delta = '') {
        $block = array();

        switch ($delta) {
            case 'fdvegan_slider_block':
                $block['subject'] = '';
                $block['content'] = _fdvegan_slider_block_content();
                break;
            case 'fdvegan_go_vegan_block':
                $block['subject'] = '';
                $block['content'] = _fdvegan_go_vegan_block_content();
                break;
            case 'fdvegan_footer_block':
                $block['subject'] = '';
                $block['content'] = _fdvegan_footer_block_content();
                break;
        }
        return $block;
    }


	 /**
      * Note - Slideshow files are automatically read from the /sites/default/files/front_slider_images dir.
      *        Any image files named "front-slide-*.png" will be used.
      */
    function _fdvegan_slider_block_content() {

        $output = '';
        // Do not show the slider on small mobile device screens.
        $detect = mobile_detect_get_object();
        //fdvegan_Content::syslog('LOG_DEBUG', "mobile_detect_get_object() gives is_mobile={$detect->isMobile()}, is_tablet={$detect->isTablet()}.");
        if (!$detect->isMobile()) {
            $slider_dir = variable_get('file_public_path', conf_path() . '/files') . '/front_slider_images';
            $files = file_scan_directory($slider_dir, '/front-slide-.+\.(png|jpg|gif)$/');
            ksort($files);  // Sort by filename, just to be sure they are in order.
            $output .= '
    <div id="home-slider">
        <div class="flexslider-container">
            <div id="single-post-slider" class="flexslider">
              <ul class="slides">
';
            $loop = 0;
            foreach ($files as $absolute => $file_obj) {
                $output .= '                <li class="slide"><img src="' . $absolute . '" alt="Slide ' . ++$loop . '"/></li>' . "\n";
            }

            $output .= '              </ul><!-- /slides -->
            </div><!-- /flexslider -->
        </div>
    </div>
';
        }
        return $output;
    }


    function _fdvegan_go_vegan_block_content() {
        // The original img URL from http://www.chooseveg.com/ was http://mfa.cachefly.net/chooseveg/images/uploads/2016/03/vsg-button-en.png
        $img_tag = theme('image', array(
                         'path' => file_create_url('public://pictures/chooseveg-com_order.png'),
                         'alt' => t('Order your FREE Vegetarian Starter Guide today!'),
                         'attributes' => array('class' => 'fdvegan-go-veg-img',
                                              ),
                        ));
        $output    = l($img_tag,
                       'http://www.chooseveg.com/vsg',
                       array('html' => TRUE,
                             'external' => TRUE,
                             'attributes' => array('target'=> '_blank',
                                                   'title' => t('Choose Veg'),
                                                   'class' => 'fdvegan-go-veg-block',
                                                  ),
                            )
                      );
        return $output;
    }


    function _fdvegan_footer_block_content() {
        $front_page_link = l('5&deg;V',
                             '<front>',
                             array('html' => TRUE,
                                   'attributes' => array('title' => t('Five Degree Vegan'),
                                                        ),
                                  )
                            );
        $copyright_content = '<span class="fdv-copyright">' . t('Copyright') . ' &copy; ' . date("Y") . ' ' . $front_page_link . '</span>';
        $tmdb_link = l(t('TMDb'),
                       'http://www.themoviedb.org/',
                       array('html' => TRUE,
                             'external' => TRUE,
                             'attributes' => array('target'=> '_blank',
                                                   'title' => t('The Movie Database'),
                                                  ),
                            )
                      );
        $tmdb_content = '<span class="fdv-tmdb">' . t('Initial data provided by ') . $tmdb_link . '</span>';
        $contact_link = l(t('Contact Us'),
                          'contact',
                          array('html' => TRUE,
                                'external' => FALSE,
                                'attributes' => array('title' => t('Send us your feedback'),
                                                     ),
                               )
                         );
        $contact_content = '<span class="fdv-contact">' . $contact_link . '</span>';

        $output = '
    <div id="fdv-footer">
    ' . $copyright_content . $tmdb_content . $contact_content . '
    </div>
                  ';

        return $output;
    }


    // other block-creation code snipped...


