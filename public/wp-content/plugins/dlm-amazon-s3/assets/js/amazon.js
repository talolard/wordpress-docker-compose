jQuery( function () {
    jQuery( '.download_monitor_files' ).on( 'click', 'a.dlm_amazon_s3', function ( e ) {

        var bucket = prompt( dlm_amazon_strings.bucket_prompt );

        if ( bucket ) {
            var object = prompt( dlm_amazon_strings.object_prompt );

            if ( !object ) {
                object = '';
            }

            link = 'http://' + bucket + '.s3.amazonaws.com/' + object;

            downloadable_files_field = jQuery( this ).closest( '.downloadable_file' ).find( 'textarea[name^="downloadable_file_urls"]' );

            old = jQuery.trim( jQuery( downloadable_files_field ).val() );

            if ( old ) {
                old = old + "\n";
            }

            jQuery( downloadable_files_field ).val( old + link );

        }

        return false;
    } );
} );