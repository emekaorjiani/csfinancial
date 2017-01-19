<tr class="form-row">
        <th scope="row">
                <?php _e( 'Life License Number', 'affiliate-ltp' ); ?>
        </th>
        <td>
                <label for="life_license_number">
                <input type="text" name="life_license_number" id="life_license_number" />
                <?php _e( 'The license number authorizing an agent to sell life insurance.' ); ?>
                </label>

        </td>
</tr>
<tr class="form-row">
        <th scope="row">
                <?php _e( 'Life License Expiration Date', 'affiliate-ltp' ); ?>
        </th>
        <td>
                <label for="life_expiration_date">
                    <input type="text" name="life_expiration_date" id="life_expiration_date" class="affwp-datepicker" autocomplete="off" placeholder="<?php echo esc_attr( date_i18n( 'm/d/y', strtotime( 'today' ) ) ); ?>"/>
                    <?php _e( 'The expiration date of the licensing.' ); ?>
                </label>

        </td>
</tr>
<tr class="form-row">
        <th scope="row">
                <?php _e( 'Co-Leadership Agent', 'affiliate-ltp' ); ?>
        </th>
        <td>
                <span class="affwp-ajax-search-wrap">
                        <input class="agent-name affwp-agent-search" type="text" name="coleadership_agent_username" data-affwp-status="active" autocomplete="off" />
                        <input class="agent-id" type="hidden" name="coleadership_user_id" value="" />
                    </span>
                    <p class="description"><?php _e( 'Enter the name of the affiliate or enter a partial name or email to perform a search.', 'affiliate-wp' ); ?></p>
        </td>
</tr>
<tr class="form-row">
        <th scope="row">
                <?php _e( 'Co-Leadership Ratio', 'affiliate-ltp' ); ?>
        </th>
        <td>
            <select name="coleadership_agent_rate">
                <option value="0"></option>
                <?php foreach ($coleadership_agent_rates as $rate => $name) : ?>
                <option value="<?= $rate ?>"><?= $name; ?></option>
                <?php endforeach; ?>
            </select>
        </td>
</tr>
<?php