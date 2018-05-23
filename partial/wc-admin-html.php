<?php
/**
 * Created by PhpStorm.
 * User: robert
 * Date: 8/04/2018
 * Time: 3:55 PM
 */

?>
<tr valign="top" id="service_options">
    <td class="forminp" colspan="3" style="padding-left:0">
        <strong><?php _e( 'Service Codes', $this->id ); ?></strong>
        <br/>
        <table class="pep_transport_services widefat">
            <thead>
            <tr>
                <th class="sort">&nbsp;</th>
                <th style="text-align:left; padding: 10px;"><?php _e( 'Service', $this->id ); ?></th>
                <th style="text-align:center; padding: 10px;"><?php _e( 'Code', $this->id ); ?></th>
                <th style="text-align:left; padding: 10px;"><?php _e( 'Description', $this->id ); ?></th>
            </tr>
            </thead>
            <tbody>
			<?php
			$sort = 0;

			$this->ordered_services = [];

			foreach ( PEP_Services::services() as $code => $values ) {

				// set the service name (next-day/same-day/vip)
				$service = $values['service'] ?? '';

				// simple description of the service 'S = 0 > 25KG'
				$desc = $values['description'] ?? '';

				// create simple string for the limits (>=kg,<=kg,>=m,<=m)
				$variables = "{$values['>=kg']},{$values['<=kg']},{$values['>=m']},{$values['<=m']}";

				if ( isset( $this->pep_services[ $code ]['order'] ) ) {
					$sort = $this->pep_services[ $code ]['order'];
				}

				while ( isset( $this->ordered_services[ $sort ] ) ) {
					$sort ++;
				}

				$this->ordered_services[ $sort ] = array( $code, $service, $desc, $variables );
				$sort ++;
			}

			ksort( $this->ordered_services );

			foreach ( $this->ordered_services as $service_info ) {

//				[ $code, $service, $desc, $variables ] = $service_info;
                list($code, $service, $desc, $variables) = $service_info;
				?>
                <tr>
                    <td class="sort">
                        <input type="hidden" class="order" name="pep_transport_service[<?= $code ?>][order]"
                               value="<?= $this->pep_services[ $code ]['order'] ?? ''; ?>"/>
                    </td>

                    <td style="text-align:left" width="30%">
                        <label>
                            <input type="checkbox" name="pep_transport_service[<?= $code ?>][enabled]"
                        </label>
						<?php checked( ! empty( $this->pep_services[ $code ]['enabled'] ) ); // ! isset( $this->pep_services[ $code ]['enabled'] ) || ?>
                        <strong data-tip="<?= $code ?>" class="tips"><?= $service ?></strong>
                        <input type="hidden" name="pep_transport_service[<?= $code ?>][name]" value="<?= $service ?>">
                    </td>

                    <td style="text-align:center" width="20%">
                        <input type="hidden" name="pep_transport_service[<?= $code ?>][variables]" value="<?= $variables ?>"><?= $code ?></td>

                    <td style="text-align:left">
                        <input type="hidden" name="pep_transport_service[<?= $code ?>][description]" value="<?= $desc ?>"><?= $desc ?>
                    </td>
                </tr>
				<?php
			}
			?>
            </tbody>
        </table>
        <style type="text/css">
            .pep_transport_services {
                width: 55.5%;
            }

            .pep_transport_services td {
                vertical-align: middle;
                padding: 4px 7px;
            }

            .pep_transport_services th {
                vertical-align: middle;
                padding: 9px 7px;
            }

            .pep_transport_services th.sort {
                width: 16px;
            }

            .pep_transport_services td.sort {
                cursor: move;
                width: 16px;
                padding: 0 16px;
                background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAAHUlEQVQYV2O8f//+fwY8gJGgAny6QXKETRgEVgAAXxAVsa5Xr3QAAAAASUVORK5CYII=) no-repeat center;
            }
        </style>
        <script type="text/javascript">

            jQuery(window).load(function () {

                // Ordering
                jQuery('.pep_transport_services tbody').sortable({
                    items: 'tr',
                    cursor: 'move',
                    axis: 'y',
                    handle: '.sort',
                    scrollSensitivity: 40,
                    forcePlaceholderSize: true,
                    helper: 'clone',
                    opacity: 0.65,
                    placeholder: 'wc-metabox-sortable-placeholder',
                    start: function (event, ui) {
                        ui.item.css('background-color', '#f6f6f6');
                    },
                    stop: function (event, ui) {
                        ui.item.removeAttr('style');
                        pep_transport_services_row_indexes();
                    }
                });

                function pep_transport_services_row_indexes() {
                    jQuery('.pep_transport_services tbody tr').each(function (index, el) {
                        jQuery('input.order', el).val(parseInt(jQuery(el).index('.pep_transport_services tr')));
                    });
                }

            });

        </script>
    </td>
</tr>

