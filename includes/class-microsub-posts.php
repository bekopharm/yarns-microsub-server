<?php /**
 * Microsub Posts Class
 *
 * @author Jack Jamieson
 *
 */


class Yarns_Microsub_Posts {

	public static function init (){
		
		// Register a post type for storing aggregated posts
		register_post_type( 'yarns_microsub_post',
	    // CPT Options
	        array(
	            'labels' => array(
	                'name' => __( 'Yarns Microsub Posts' ),
	                'singular_name' => __( 'Yarns Microsub Post' )
	            ),
	            'public' => true,
	            'has_archive' => false,
	            'rewrite' => array('slug' => 'yarns_post'),
	        )
	    );

	    // register custom taxonomy to group aggregated posts by channel
	    register_taxonomy(  
        	'yarns_microsub_post_channel',  //The name of the taxonomy. Name should be in slug form (must not contain capital letters or spaces). 
        	'yarns_microsub_post',       //post type name
	        array(  
	            'hierarchical' => false,  
	            'label' => 'Channel',  //Display name
	            'query_var' => true,
	            'rewrite' => array(
	                'slug' => 'channel' // This controls the base slug that will display before each term
	            )
	        )  
	    );  



	    
	}
	


	public static function add_post($permalink, $post, $channel){

	

		$my_post = array(
		  'post_type'	  => 'yarns_microsub_post',
		  'post_title'    => $channel."|".$permalink,
		  'post_content'  => json_encode($post),
		  'post_status'   => 'publish'

		);


		if (isset($post['published'])){
			//return $post['published'];
			$my_post["post_date"] = date('Y-m-d H:i:s', strtotime($post['published']));
			
			//$my_post["post_date"] = strtotime($post['published']);
			//return json_encode($my_post);
		}
		if (isset($post['updated'])){
			$my_post["post_modified"] = date('Y-m-d H:i:s', strtotime($post['updated']));

		}

		return $post;

		// Create the post
		$post_id = wp_insert_post( $my_post );

		// Set the channel of the post 
		wp_set_post_terms( $post_id, $channel, 'yarns_microsub_post_channel' );

		// Return the post id
		return $post_id;

	}

	
}

?>