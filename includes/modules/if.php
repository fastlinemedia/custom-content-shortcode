<?php
/*---------------------------------------------
 *
 * [if] - Display content based on conditions
 *
 * TODO: Add filters and modularize!
 *
 */

new CCS_If;

class CCS_If {

  public static $state;
  public static $vars;

  function __construct() {

    self::$state['is_if_block'] = false;
    self::$vars = array();

    add_ccs_shortcode( array(
      'if' => array( $this, 'if_shortcode' ),
      '-if' => array( $this, 'if_shortcode' ),
      '--if' => array( $this, 'if_shortcode' ),
      '---if' => array( $this, 'if_shortcode' ),
      '----if' => array( $this, 'if_shortcode' ),
      'var' => array( $this, 'var_shortcode' ),
      'switch' => array( $this, 'switch_shortcode' ),
    ));

    add_local_shortcode('ccs_switch', 'when', array( $this, 'when_shortcode' ));
    add_local_shortcode('ccs_switch', 'case', array( $this, 'when_shortcode' ));
  }



  function if_shortcode( $atts, $content = null, $shortcode_name ) {

    $atts_original = $atts;

    $args = array(

      'type' => '',
      'name' => '',
      'id' => '',
      'author' => '',
      'comment_author' => '', // Check for comment by user ID or name

      'category' => '',
      'tag' => '',
      'taxonomy' => '',
      'term' => '',
      'compare' => '',
      'tax_archive' => '',

      'parent' => '',

      'field' => '',
      'custom' => '', // SKip predefined field names
      'user_field' => '',
      'check' => '', // Check passed value
      'value' => '',

      // Date field comparison
      'before' => '',
      'after' => '',
      'date_format' => '',
      'in' => '', // For date-time field, set in=timestamp or date_format=U
      'field_2' => '', // Optional with before/after

      'lowercase' => '',
      'case' => '', // Alias: opposite of lowercase
      'loose' => '', // lowercase and normalize special characters - default
      'strict' => '', // Alias: opposite of loose

      'sub' => '', // For array
      'format' => '', // post format

      'contains' => '', // Search field value
      'exact' => '',

      'empty' => 'true', // If value parameter is empty
      'zero' => '', // If field is empty
      'sticky' => '',

      'not' => '',
      'start' => '',
      'end' => '',

      'every' => '',
      'first' => '',
      'last' => '',
      'count' => '',

      // Inside for/each loop
      'each' => '',
      'each_field' => '',
      'each_value' => '',

      // CCS_Format::x_shortcode
      'x' => '',

      'query' => '',
      'route' => '',

      'pass' => '',

      'false' => '', // Force false

      // deprecated
      'pass_empty' => 'true',
      'flag' => '',
      'no_flag' => '',
    );

    extract( shortcode_atts( $args , $atts, true ) );

    $atts = CCS_Content::get_all_atts( $atts );

    if ( isset($atts['and']) ) {
      return self::if_all_conditions( $atts, $content, $shortcode_name );
    }

    if ( count($atts)==0 ) $condition = true;
    else $condition = false;


    $out = '';

    // Get [else] block
    $if_else = self::get_if_else( $content, $shortcode_name );
    $content = $if_else['if'];
    $else = $if_else['else'];


    if ( ( !empty($before) || !empty($after) ) && empty($field) ) {
      $field = 'date'; // Default for before/after parameter
    }
    if ( isset($atts['today']) ) {
      $field = 'date';
      $value = 'today';
    }
    if (!empty($no_flag)) $flag = $no_flag;

    $compare = strtoupper($compare);

    if ( $strict=='true' ) $loose = 'false'; // Alias
    if ( $case=='true' ) $lowercase = 'false'; // Alias


    /*---------------------------------------------
     *
     * Get global post info
     *
     */

    $current_post_id = do_shortcode('[field id]');
    $post = get_post($current_post_id);


    /*---------------------------------------------
     *
     * If exists
     *
     */

    if (isset($atts['exists'])) {

      unset($atts['exists']);
      if (empty($atts)) {
        $result = do_ccs_shortcode($content);
      } else {
        // Check if post exists based on given parameters
        $result = CCS_Loop::the_loop_shortcode($atts_original, 'X');
        // Remove params for rest of condition checks
        $atts = array();
      }
      $result = trim($result);
      $condition = !empty($result);
    }


    /*---------------------------------------------
     *
     * Taxonomy: category, tags, ..
     *
     */

    if (!empty($category)) {
      $taxonomy = "category";
      $term = $category;
    }

    if (!empty($tag)) {
      $taxonomy = "post_tag";
      $term = $tag;
    }

    // Check if current post has taxonomy term

    if ( !empty($taxonomy) ) {

      if ($taxonomy == 'tag') $taxonomy = 'post_tag';

      $taxonomies = wp_get_post_terms(
        $current_post_id,
        $taxonomy, array() );

      $post_tax_array = array();
      foreach ($taxonomies as $term_object) {
        if ( is_object($term_object) )
          $post_tax_array[] = $term_object->slug;
      }

      $terms = self::comma_list_to_array($term);

      if ( empty($term) && count($post_tax_array) ) {

        // If no term query is set, then check if there's any term
        $condition = true;

      } else {
        foreach ($terms as $term) {

          if ( empty($compare) || $compare == 'OR' ) {
            $condition = in_array($term, $post_tax_array) ? true : $condition;
          } else {
            // AND
            $condition = in_array($term, $post_tax_array) ? true : false;
            if (!$condition) break; // Every term must be found
          }
        }
      }

    }

    /*---------------------------------------------
     *
     * Inside [for/each] loop
     *
     */

    // Check if current term has children

    if ( CCS_ForEach::$state['is_for_loop'] ) {

      if ( isset($atts['children']) ) {
        $current_term = CCS_ForEach::$current_term[ CCS_ForEach::$index ];
        $current_taxonomy = $current_term['taxonomy'];

        $terms = get_terms( $current_taxonomy, array('parent' => $current_term['id']) );

        if (!empty($terms) && $terms!=array()) $condition = true;
        else $condition = false;

      }

      if ( !empty($each) ) {
        $v = do_shortcode('[each slug]');
        $eaches = CCS_Loop::explode_list($each);
        $condition = in_array($v, $eaches);
      }
      if ( !empty($each_field) ) {
        $v = do_shortcode('[each '.$each_field.']');
        if ( !empty($each_value) ) $condition = ($v == $each_value);
        else $condition = !empty($v);
      }

      if ( isset($atts['for-first']) ) {
        $condition = $condition || CCS_ForEach::$state['for_count'] == 1;
      }
    }


    /*---------------------------------------------
     *
     * Field value contains
     *
     */

    if ( !empty($contains) ) {

      if ( $exact === 'true' ) {
        $value = $contains;
      } else {
        $value = implode(',', explode(' ', $contains));
      }

      $contains_compare = !empty($compare) ? strtoupper($compare) : '';
      $compare = 'CONTAINS';
      if (empty($loose)) $loose = 'true'; // Loose search by default

      $field = CCS_Loop::explode_list($field);

      if ( count($field) > 1 ) {

        // Support searching multiple fields

        foreach ($field as $this_field) {

          $condition =
            '[if field='.$this_field
              .' contains="'.$value.'"'
              . ( !empty($contains_compare) ? ' compare='.$contains_compare : '' )
            .'].[/if]';

          $condition = do_shortcode( $condition );
          $condition = !empty( $condition );
          if ( $condition ) break; // If keyword in any of the fields
        }

        $field = ''; // Skip default field value condition
      } else {
        $field = $field[0];
      }
    }



    /*---------------------------------------------
     *
     * Field: field="field_slug" value="this,that"
     *
     */

    if ( !empty($field) || !empty($user_field) || !empty($check) ) {

      // Post field
      if ( !empty($field) ) {

        if ($in=='timestamp') {
          $date_format = 'U';
          if ($value=='today')
            $value = 'today-between';
        }

        if ( $field == 'date' ) {

          if (empty($date_format)) $date_format = 'Ymd';

          // Published date
          $check = strtotime( $post->post_date . ' +0000');
          $check = date( $date_format, $check );

        } elseif ( $field == 'excerpt' ) {

          $check = get_the_excerpt();
          $empty = 'true';
          $value = ''; // ?

        } elseif ( $custom == 'true' ) {

          $check = get_post_meta( $current_post_id, $field, $single = true );

        } else {

          // Normal field
          $check = CCS_Content::get_prepared_field( $field ); // do_shortcode('[field '.$field.']');// ;
        }


        // Date field

        if ( !empty($before) || !empty($after) ) {

          if ( !empty($field_2) ) {
            $field_2 = CCS_Content::get_prepared_field( $field_2 );
            // Convert to imestamp
            if ($in!=='timestamp') $field_2 = strtotime( $field_2 . ' +0000');
            $now = intval($field_2);
  //echo 'FIELD2:'.$field_2.' = '.$now.'<br>';
          } else {
            $now = current_time('timestamp');
          }

          if ( !empty($before) && !empty($after) ) {

            $value_before = date( $date_format, strtotime( $before . ' +0000', $now ) );
            $value_after = date( $date_format, strtotime( $after . ' +0000', $now ) );

            $value =  $value_before.' - '.$value_after;
            $compare = 'BETWEEN';

          } elseif ( !empty($before) ) {
            $value = date( $date_format, strtotime( $before . ' +0000', $now ) );
            $compare = empty($compare) ? '<' : $compare;
          } elseif ( !empty($after) ) {
            $value = date( $date_format, strtotime( $after . ' +0000', $now ) );
            $compare = empty($compare) ? '>' : $compare;
  //echo 'VALUE: '.$now.' '.$after.' = '.$value.'<br>';
          }

        } elseif ( !empty($field_2) ) {
          // Use second field as value
          $value = CCS_Content::get_prepared_field( $field_2 );
          if ( !empty($date_format) ) {
            $check = date( $date_format, strtotime( $check . ' +0000' ) );
            $value = date( $date_format, strtotime( $value . ' +0000' ) );
          }
        }

      // User field
      } elseif ( !empty($user_field) ) {

        $field = $user_field;
        $check = CCS_User::get_user_field( $field );

        if (!empty($sub)) {
          $check = isset($check[$sub]) ? $check[$sub] : '';
        }

      } else {
        // Just check passed value
        // $check == $value
      }



      // Array
      if ( !empty($sub) ) {
        $check = isset($check[$sub]) ? $check[$sub] : '';
      }

      // start=".." end=".."
      if ( !empty($start) && !empty($end) ) {

        $value = $start.'..'.$end; // Placeholder
        $start_value = $start;
        $end_value = $end;
        $start = 'true';
        $end = 'true';

      // start=".."
      } elseif ( !empty($start) && ($start!='true') && empty($value) ) {
        $value = $start;
        $start = 'true';
      // end=".."
      } elseif ( !empty($end) && ($end!='true') && empty($value) ) {
        $value = $end;
        $end = 'true';
      }

      if ( $check === '' ) { // ( empty($check) || ( $check == false ) ) {
        // @todo What if field value is boolean, i.e., checkbox?

        $condition = false;

      } else {

        if ( !is_array($check) ) $check = array($check);

        if ( $value !== '' ) { // Allow false, 0

          $values = self::comma_list_to_array($value);
          $date_values = array(
            'today', 'today-between', 'now','future','past','future not today','past and today','future-time','past-time'
          );


          foreach ($check as $check_this) {

            foreach ($values as $this_value) {

              if (in_array($this_value, $date_values)) {

                if (empty($date_format)) $date_format = 'Ymd'; // ACF date field

                // Support date values
                $q = array(
                  'field' => $check_this,
                  'value' => $this_value,
                  'compare' => $compare,
                  'date_format' => $date_format
                );

                $meta_query = CCS_Loop::prepare_meta_query( $q );

                $check_this = $meta_query['key'];
                $this_value = $meta_query['value'];
                $compare = $meta_query['compare'];
                //debug_array($q);
                if (is_array($this_value)) $this_value = implode(',', $this_value);
              }

//echo 'Check field: '.$field.' '.$check_this.' '.$compare.' '.$this_value.'<br>';

              if ( $start == 'true' && $end == 'true' ) {
                // Check beginning and end of field value
                if ( substr($check_this, 0, strlen($start_value)) == $start_value &&
                  substr($check_this, strlen($check_this) - strlen($end_value) ) == $end_value ) {
                  $condition = true;
                  continue;
                } else {
                  $condition = false;
                  break;
                }

              } elseif ( $start == 'true' ) {
                // Only check beginning of field value
                $check_this = substr($check_this, 0, strlen($this_value));
              } elseif ( $end == 'true' ) {
                // Only check end of field value
                $check_this = substr($check_this, strlen($check_this) - strlen($this_value));
              }

              if ( $loose == 'true' ) {
                $check_this = CCS_Format::normalize_alphabet($check_this);
                $this_value = CCS_Format::normalize_alphabet($this_value);
              }
              if ( $loose == 'true' || $lowercase == 'true' ) {
                $check_this = strtolower($check_this);
                $this_value = strtolower($this_value);
              }

              if ($compare == 'AND') {

                $condition = ($this_value==$check_this) ? true : false;
                if (!$condition) break; // Every term must be found

              } elseif ($compare == 'CONTAINS') {

                $condition = strpos($check_this, $this_value) !== false;

                if ( $contains_compare != 'OR' && !$condition ) {
                  break; // Every term must be found
                }

              } else {

                switch ($compare) {
                  case 'MORE':
                  case 'NEW':
                  case 'NEWER':
                  case '>':
                    $condition = ($check_this > $this_value) ? true : $condition;
                  break;
                  case '>=':
                    $condition = ($check_this >= $this_value) ? true : $condition;
                  break;
                  case 'LESS':
                  case 'OLD':
                  case 'OLDER':
                  case '<':
                    $condition = ($check_this < $this_value) ? true : $condition;
                  break;
                  case '<=':
                    $condition = ($check_this <= $this_value) ? true : $condition;
                  break;
                  case 'BETWEEN':
                    $values = explode(' - ', $this_value); // Hmm..to avoid conflict with ','
                    if (isset($values[0]) && isset($values[1])) {
                      $condition =
                        ($values[0] <= $check_this && $check_this <= $values[1]) ?
                          true : $condition;
                    }
                  break;
                  case 'EQUAL':
                  case '=':
                  default:
                    $condition = ($check_this == $this_value) ? true : $condition;
                  break;
                }

              } // End compare
            } // End for each value
          } // End for each check

        } // Value is not null - allow: false, 0

        else {

          // No value specified - just check that there is field value
          if ( $empty=='true' ) {
              if (is_array($check)) $check = implode('', $check); // catches ACF repeater
              $condition = !empty($check) ? true : false;
          } else {
            $condition = false;
          }
        }
      } // End if check not empty

    } // End field value condition


    /*---------------------------------------------
     *
     * Post type, name, id
     *
     */

    if ( !empty($type) ) {

      $types = self::comma_list_to_array($type); // Enable comma-separated list

      $current_post_type = isset($post->post_type) ? $post->post_type : null;
      $condition = in_array($current_post_type, $types) ? true : false;
    }

    if ( !empty($id) ) {

      $ids = self::comma_list_to_array($id); // Enable comma-separated list
      if ( ($find_key = array_search('this', $ids)) !== false ) {
        $depth = CCS_Content::$state['depth'];
        if ( isset(CCS_Content::$state['current_post_id'][ $depth - 1 ])) {
          $ids[$find_key] = CCS_Content::$state['current_post_id'][ $depth - 1 ];
        } elseif (CCS_Loop::$state['is_loop']) {
          $ids[$find_key] = CCS_Loop::$state['original_post_id'];
        } else {
          $ids[$find_key] = get_the_ID();
        }
      }

      $condition = in_array($current_post_id, $ids) ? true : false;
    }

    if ( !empty($name) ) {

      $names = self::comma_list_to_array($name);
      $current_post_name = isset($post->post_name) ? $post->post_name : null;

      foreach ($names as $each_name) {

        if ( $start == 'true' ) {

          // Only check beginning of string
          $this_value = substr($current_post_name, 0, strlen($each_name));

        } else {
          $this_value = $current_post_name;
        }

        $condition = ($this_value == $each_name) ? true : $condition;
      }
    }


    /*---------------------------------------------
     *
     * Post author
     *
     */

    if ( !empty($author) ) {

      $authors = CCS_Loop::explode_list( $author );
      $author_ids = array();
      foreach ($authors as $this_author) {
        if ( $this_author=='this' ) {
          // current author ID
          $author_ids[] = do_shortcode('[user id]');
        } elseif (is_numeric( $this_author )) {
          $author_ids[] = $this_author;
        } else {
          // get author ID from user name
          $author_ids[] = do_shortcode('[users search='.$this_author.' search_column=login][user id][/users]');
        }
      }

      if ( CCS_Comments::$state['is_comments_loop'] ) {
        $post_id = do_shortcode('[comment post-id]');
      } else {
        $post_id = do_shortcode('[field id]');
      }
      $pass = do_shortcode('[field author-id id='.$post_id.']');
      if (empty($pass)) {
        $condition = false;
      } else {
        $value = implode(',', $author_ids);
      }
    }


    if ( !empty($comment_author) ) {
      if (CCS_Comments::$state['is_comments_loop']) {

        $authors = CCS_Loop::explode_list( $comment_author );
        $author_ids = array();
        foreach ($authors as $this_author) {
          if ( $this_author=='this' ) {
            // current author ID
            $author_ids[] = do_shortcode('[user id]');
          } elseif ( $this_author=='same' ) {
            // Same author as current post
            if ( $current_post ) {
              $author_ids[] = do_shortcode('[field author-id]');
            }

          } elseif (is_numeric( $this_author )) {
            $author_ids[] = $this_author;
          } else {
            // get author ID from user name
            $author_ids[] = do_shortcode('[users search='.$this_author.' search_column=login][user id][/users]');
          }
        }
        $check_author = do_shortcode('[comment author-id]');
        $condition = in_array($check_author, $author_ids);

      } else {
        $this_check = do_shortcode('[comments user='.$comment_author.' count=1].[/comments]');
        $condition = !empty($this_check);
      }
    }

    /*---------------------------------------------
     *
     * Post parent
     *
     */

    if (!empty($parent)) {

      $current_post_parent = isset($post->post_parent) ? $post->post_parent : 0;

      if ($current_post_parent == 0) {
        // Current post has no parent
        $condition = false;
      } else {

        $current_post_parent_slug = self::slug_from_id($current_post_parent);
        $parents = self::comma_list_to_array($parent);

        foreach ($parents as $check_parent) {

          if (is_numeric($check_parent)) {
            // compare to parent id

            if ( empty($compare) || $compare == 'OR' ) {
              $condition = ($check_parent==$current_post_parent) ? true : $condition;
            } else { // AND
              $condition = ($check_parent==$current_post_parent) ? true : false;
              if (!$condition) break; // Every term must be found
            }

          } else {
            // compare to parent slug

            if ($start=='true') {
              // Only check beginning of string
              $check_this = substr($current_post_parent_slug, 0, strlen($check_parent));
            } else {
              $check_this = $current_post_parent_slug;
            }

            if ( empty($compare) || $compare == 'OR' ) {
              $condition = ($check_parent==$check_this) ? true : $condition;
            } else { // AND
              $condition = ($check_parent==$check_this) ? true : false;
              if (!$condition) break; // Every term must be found
            }
          }
        }
      }
    }


    /*---------------------------------------------
     *
     * Attachments
     *
     */

    if ( isset($atts['attached']) ) {

      // Does the current post have any attachments?

      $current_id = get_the_ID();
      $posts = get_posts( array (
        'post_parent' => $current_id,
        'post_type' => 'attachment',
        'post_status' => 'any',
        'posts_per_page' => 1,
        ) );
      if (!empty($posts)) $condition = true;
      else $condition = false;
    }


    /*---------------------------------------------
     *
     * If child post exists
     *
     */

    if ( isset($atts['children']) && !CCS_ForEach::$state['is_for_loop'] && !CCS_Menu::$state['is_menu_loop'] ) {

      if (!empty($post)) {
        $children_array = get_children( array(
            'post_parent' => $post->ID,
            'posts_per_page' => '1',
            'post_status' => 'publish' )
        );
        $condition = ( count( $children_array ) > 0 );
      }
    }



    /*---------------------------------------------
     *
     * [x] loop index
     *
     */

    if (!empty($x)) {
      $condition = ( $x == CCS_Format::$state['x_loop'] );
    }


    /*---------------------------------------------
     *
     * Sticky post
     *
     */

    if (isset($atts['sticky'])) $sticky = 'true';
    if ( !empty($sticky) ){
      $is_sticky = is_sticky();
      $condition = ( $is_sticky && $sticky=='true' ) || ( !$is_sticky && $sticky=='false' );
    }

    /*---------------------------------------------
     *
     * Post format
     *
     */

    if ( !empty($format) && function_exists( 'has_post_format' ) ) {
      $formats = CCS_Loop::explode_list($format);
      foreach ($formats as $this_format) {
        $this_format = strtolower($this_format);
        if ( has_post_format( $this_format, $current_post_id ) ) {
          $condition = true;
          break;
        }
      }
    } elseif ( isset($atts['format']) ) {
      // Check if it exists
      $this_format = get_post_format( $current_post_id );
      if (!empty($this_format)) $condition = true;
    }

    /*---------------------------------------------
     *
     * Has CCS gallery field
     *
     */

    if ( isset($atts['gallery']) && class_exists('CCS_Gallery_Field')) {
      $condition =  CCS_Gallery_Field::has_gallery();
    }


    /*---------------------------------------------
     *
     * Template: home, archive, single..
     * [if comment] - current post has comment
     *
     */

    $condition = isset($atts['home']) ? is_front_page() : $condition;
    $condition = isset($atts['comment']) ? (get_comments_number($current_post_id)>0) : $condition;
    $condition = isset($atts['image']) ? has_post_thumbnail() : $condition;
    $condition = isset($atts['loop']) ? ( CCS_Loop::$state['is_loop'] ) : $condition;
    $condition = isset($atts['archive']) ? is_archive() : $condition;
    $condition = isset($atts['single']) ? is_single() : $condition;
    $condition = isset($atts['search']) ? is_search() : $condition;
    $condition = isset($atts['404']) ? is_404() : $condition;
    $condition = isset($atts['none']) ? !have_posts() : $condition;

    if (isset($atts['tax_archive'])) {
      if ($tax_archive == 'true') $tax_archive = '';
      $condition = is_tax( $tax_archive );
    }

    if (isset($atts['avatar'])) {
      $check = do_shortcode('[field avatar]');
      $condition = !empty($check);
    }

    /*---------------------------------------------
     *
     * Inside [loop]
     *
     */

    if ( CCS_Loop::$state['is_loop'] ) {

      /*---------------------------------------------
       *
       * Every X number of posts
       *
       */

      if ( !empty($every) ) {

        $count = CCS_Loop::$state['loop_count'];

        if (substr($every,0,4)=='not ') {
          $every = substr($every, 4); // Remove first 4 letters

          // not Modulo
            $condition = ($every==0) ? false : (($count % $every)!=0);
        } else {

          // Modulo
          $condition = ($every==0) ? false : (($count % $every)==0);
        }

        if ($first == 'true') {
          $condition = $condition || CCS_Loop::$state['loop_count'] == 1;
          //unset($atts['first']);
        }
        if ($last == 'true') {
          $condition = $condition || CCS_Loop::$state['loop_count'] == CCS_Loop::$state['post_count'];
          //unset($atts['last']);
        }

      } elseif ( !empty($count) ) {

        if ( $compare == '>=' ) {
          $condition = CCS_Loop::$state['loop_count'] >= $count;
        } elseif ( $compare == '<=' ) {
          $condition = CCS_Loop::$state['loop_count'] <= $count;
        } elseif ( $compare == '>' || $compare == 'MORE' ) {
          $condition = CCS_Loop::$state['loop_count'] > $count;
        } elseif ( $compare == '<' || $compare == 'LESS' ) {
          $condition = CCS_Loop::$state['loop_count'] < $count;
        } else {
          $condition = CCS_Loop::$state['loop_count'] == $count;
        }

      }

      /*---------------------------------------------
       *
       * First and last post in loop
       *
       */

      $condition = isset($atts['first']) ?
        CCS_Loop::$state['loop_count'] == 1 : $condition;

      $condition = isset($atts['last']) ?
        CCS_Loop::$state['loop_count'] == CCS_Loop::$state['post_count'] : $condition;


    } // End: if inside [loop]


    /*---------------------------------------------
     *
     * Menu loop
     *
     */

    if ( CCS_Menu::$state['is_menu_loop'] ) {

      $condition = isset($atts['first']) ?
        CCS_Menu::$state['menu_index'][ CCS_Menu::$state['depth'] ] == 1 : $condition;

      $condition = isset($atts['last']) ?
        CCS_Menu::$state['menu_index'][ CCS_Menu::$state['depth'] ] ==
          CCS_Menu::$state['total_menu_count'][ CCS_Menu::$state['depth'] ]
        : $condition;

      if (isset($atts['children'])) {
        $children = do_shortcode('[loop menu=children].[/loop]');
        if (!empty($children)) $condition = true;
        else $condition = false;
      }


      if ( !empty($every) ) {

        $count = CCS_Menu::$state['menu_index'][ CCS_Menu::$state['depth'] ];
        // Modulo
        $condition = ($every==0) ? false : (($count % $every)==0);
      }
    }


    /*---------------------------------------------
     *
     * Every X number of repeater
     *
     */

    if ( class_exists('CCS_To_ACF') && CCS_To_ACF::$state['is_repeater_or_flex_loop'] ) {

      if ( !empty($every) ) {
        $condition = ( CCS_To_ACF::$state['repeater_index'] % $every == 0 );
      }
      if ( isset($atts['first']) ) {
        $condition = ( CCS_To_ACF::$state['repeater_index'] == 1 );
      }
    }

    /*---------------------------------------------
     *
     * Inside array field
     *
     */

    if (CCS_Content::$state['is_array_field']) {
      if ( isset($atts['first']) ) {
        $condition = ( CCS_Content::$state['array_field_index'] == 1 );
      }
      if ( isset($atts['last']) ) {
        $condition = ( CCS_Content::$state['array_field_index'] == CCS_Content::$state['array_field_count'] );
      }
    }

    /*---------------------------------------------
     *
     * Inside comments loop
     *
     */

    if (CCS_Comments::$state['is_comments_loop']) {
      if ( isset($atts['first']) ) {
        $condition = ( CCS_Comments::$state['comments_loop_index'] == 1 );
      }
      if ( isset($atts['last']) ) {
        $condition = ( CCS_Comments::$state['comments_loop_index'] == CCS_Comments::$state['comments_loop_count'] );
      }
    }


    /*---------------------------------------------
     *
     * Passed value
     *
     */

    if ( ( isset($atts['pass']) && empty($atts['pass']) && $empty!='true' ) ||
      ( $pass_empty!='true' && empty($pass) ) ) // @todo deprecated
    {

      // pass="{FIELD}" empty="false" -- pass is empty

      $condition = false;

    } elseif ( !empty($pass) && empty($value) && $empty!='true' ) {

      // pass="{FIELD}" empty="false" -- no value set

      $condition = true;

    } elseif ( !empty($pass) && !empty($value) ) {

      // pass="{FIELD}" value="something"

      $values = CCS_Loop::explode_list( $value ); // Support multiple values

      $condition = in_array( $pass, $values );
    }


    /*---------------------------------------------
     *
     * Query
     *
     */

    $queries = CCS_URL::get_queries();
    if (!empty($query)) {
      $query_check = isset($queries[$query]) ? $queries[$query] : '';
      if (!empty($value)) {
        $condition = $query_check == $value;
      } else {
        $condition = !empty($query_check);
      }
    }

    /*---------------------------------------------
     *
     * Route
     *
     */

    $routes = CCS_URL::get_routes();
    for ($i=0; $i < count($routes); $i++) {
      if (isset($atts['route_'.($i+1)])) {
        $condition = $atts['route_'.($i+1)] == $routes[$i];
        if (!$condition) break;
      } elseif ( isset($atts[0]) && $atts[0] == 'route_'.($i+1) ) {
        // if it exists
        $condition = !empty($routes[$i]);
        if (!$condition) break;
      }
    }


    /*---------------------------------------------
     *
     * Not / else
     *
     */

    // Not - also catches compare="not"
    $condition = isset($atts['not']) ? !$condition : $condition;

    self::$state['is_if_block'] = true;
    $out = $condition ?
      do_ccs_shortcode( $content ) :
      do_ccs_shortcode( $else ); // [if]..[else]..[/if]
    self::$state['is_if_block'] = false;

    return $out;
  }



  // Returns array with if and else blocks
  public static function get_if_else( $content, $shortcode_name = '', $else_name = 'else' ) {

    // Get [else] if it exists

    $prefix = CCS_Format::get_minus_prefix( $shortcode_name );
    $else_name = $prefix.$else_name;

    $content_array = explode('['.$else_name.']', $content);
    $content = $content_array[0];
    if ( count($content_array)>1 ) {
      $else = $content_array[1];
    } else {
      $else = '';
    }

    return array(
      'if' => $content,
      'else' => $else
    );
  }


  /*---------------------------------------------
   *
   * Handle multiple conditions with "and"
   *
   */

  static function if_all_conditions( $atts, $content = null, $tag ) {

    // $atts must be pre-processed by CCS_Content::get_all_atts

    $num_conditions = 1;
    $operator = 'or';

    $conditions = '['.$tag;

    foreach ($atts as $key => $value) {

      $check = explode('__', $key);
      if ( isset($check[0]) && $check[0]=='and' ) {
        $operator = 'and';
        $num_conditions++;
        $conditions .= ']X[/'.$tag.']['.$tag.' ';
      } else {
        $conditions .= ' '.$key.'='.$value;
      }
    }
    $conditions .= ']X[/'.$tag.']';

    $result = do_ccs_shortcode( $conditions );


    if ( $operator == 'or' )
      $condition = ( ! empty($result) );
    else {
      // Must fulfill all conditions
      $condition = ( strlen($result) >= $num_conditions );
    }

    $condition = $condition ? '' : ' false';

    $out = do_ccs_shortcode('['.$tag.$condition.']'.$content.'[/'.$tag.']');

    return $out;
  }


  /*---------------------------------------------
   *
   * Switch/when
   *
   */

  function switch_shortcode( $atts, $content ) {

    $switch = '';
    if (isset($atts[0])) $switch = $atts[0];
    else {
      // Pass to [if]
      foreach ($atts as $key => $value) {

        if ( is_numeric($key) ) continue;

        $switch = $key.'='.$value;
        // Add trailing parameter for when/case
        switch ($key) {

          case 'taxonomy':
            $switch .= ' term';
          break;

          case 'field': // Fall through
          case 'user_field': // Fall through
          default:
            $switch .= ' value';
          break;
        }
      }
    }

    if (empty($switch)) return;

    self::$state['current_switch'] = $switch;
    self::$state['current_switch_default'] = '';

    $result = do_local_shortcode('ccs_switch', $content);

    // If empty, do default

    $check = trim($result); // Strip new lines
    if (empty( $check ))
      $result = self::$state['current_switch_default'];


    self::$state['current_switch'] = '';
    self::$state['current_switch_default'] = '';

    if (!empty($result))
      $result = do_ccs_shortcode( $result );

    return $result;
  }

  function when_shortcode( $atts, $content ) {
    if (!isset($atts[0])) return;
    $switch = self::$state['current_switch'];
    $when = $atts[0];
    if ($when == 'default') {
      self::$state['current_switch_default'] = $content;
      return;
    }
    return do_ccs_shortcode('[if '.$switch.'='.$when.']'.$content.'[/if]');
  }


  /*---------------------------------------------
   *
   * Other
   *
   */

  function var_shortcode( $atts ) {

    foreach ($atts as $key => $value) {

      if ( substr($value, 0 , 1) == '+' ) {

        $value = substr($value, 1);
        if (!isset(self::$vars[ $key ])) self::$vars[ $key ] = 0;
        self::$vars[ $key ] += $value;

      } elseif ( substr($value, 0 , 1) == '-' ) {

        $value = substr($value, 1);
        if (!isset(self::$vars[ $key ])) self::$vars[ $key ] = 0;
        self::$vars[ $key ] -= $value;

      } elseif (is_numeric($key)) {

        // [var x] returns the value
        return isset(self::$vars[ $value ]) ? self::$vars[ $value ] : '';

      } else {
        self::$vars[ $key ] = $value;
      }
    }
  }


  /*---------------------------------------------
   *
   * Utilities
   *
   * TODO: Put these in CCS_Format as general-purpose functions
   *
   */

  function comma_list_to_array( $string ) {

    // Explode comma-separated list and trim white space

    return array_map("trim", explode(",", $string));
  }

  function slug_from_id( $id ) {
    $post_data = get_post($id);
    if (!empty($post_data)) {
      return isset($post_data->post_name) ? $post_data->post_name : null;
    } else return null;
  }

}
