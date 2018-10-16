<div class='alignleft'>
	<div class='http-status'><?php _e( '1xx Informational:', 'simply-static-github-sync' ); ?> <b><?php echo $this->http_status_codes['1']; ?></b> |
		<?php _e( '2xx Success:', 'simply-static-github-sync' ); ?> <b><?php echo $this->http_status_codes['2']; ?></b> |
		<?php _e( '3xx Redirection:', 'simply-static-github-sync' ); ?> <b><?php echo $this->http_status_codes['3']; ?></b> |
		<?php _e( '4xx Client Error:', 'simply-static-github-sync' ); ?> <b><?php echo $this->http_status_codes['4']; ?></b> |
		<?php _e( '5xx Server Error:', 'simply-static-github-sync' ); ?> <b><?php echo $this->http_status_codes['5']; ?></b> |
		<?php _e( "<a href='https://en.wikipedia.org/wiki/List_of_HTTP_status_codes'>More info on HTTP status codes</a>", 'simply-static-github-sync' ); ?></div>
</div>

<div class='tablenav-pages'>
	<span class='displaying-num'><?php echo sprintf( __( "%d URLs", 'simply-static-github-sync' ), $this->total_static_pages );?></span>
	<?php
		$args = array(
			'format' => '?page=%#%',
			'total' => $this->total_pages,
			'current' => $this->current_page,
			'prev_text' => '&lsaquo;',
			'next_text' => '&rsaquo;'
		);
		echo paginate_links( $args );
	?>
</div>
