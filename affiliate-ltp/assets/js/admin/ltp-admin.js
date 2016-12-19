(function($) {
    
    var rowId = 1;
    
    /**
     * Clears up the search and resets all the fields
     * @returns
     */
    function resetClientSearch() {
        $('.affwp-client-search').val('');
        $( '#client_id' ).val("");

        var items = [
            "client_name"
            ,"client_street_address"
            ,"client_city_address"
            ,"client_zip_address"
            ,"client_phone"
            ,"client_email"
        ];
        items.forEach(function(item) {
            $('#' + item).prop("readonly", false)
                    .val("");
        });
        
        $('.readonly-description').addClass('hidden');
    }
    function setupAgentSearch(selector) {
        		var	$this    = $( selector ),
			$action  = 'affwp_search_users',
			$search  = $this.val(),
			$status  = $this.data( 'affwp-status'),
			$agent_id = $this.siblings(".agent-id");

		$this.autocomplete( {
			source: ajaxurl + '?action=' + $action + '&term=' + $search + '&status=' + $status,
			delay: 500,
			minLength: 2,
			position: { offset: '0, -1' },
			select: function( event, data ) {
				$agent_id.val( data.item.user_id );
			},
			open: function() {
				$this.addClass( 'open' );
			},
			close: function() {
				$this.removeClass( 'open' );
			}
		} );

		// Unset the user_id input if the input is cleared.
		$this.on( 'keyup', function() {
			if ( ! this.value ) {
				$agent_id.val( '' );
			}
		} );
    }
    // allows us to change this around if needed.
    function getRowId() {
        return rowId++;
    }
    
    /**
     * Adds a row to the split list that an agent and a split percentage can be added to.
     * @param Event evt
     * @param number agentSplit The percentage of the commission that goes to the agent.
     * @returns {undefined}
     */
    function addSplitRow(evt, agentSplit) {
        if (!agentSplit) {
            agentSplit = 0;
        }
        
        var rowId = getRowId();
        
        var splitRow = [];
           splitRow.push("<tr>");
           
           // agent search
           splitRow.push("<td>");
           splitRow.push("<span class='affwp-ajax-search-wrap'>");
           splitRow.push("<input class='agent-name affwp-agent-search' type='text' name=\"agents[" + rowId + "]['agent_name']\" data-affwp-status='active' autocomplete='off' />");
           splitRow.push("<input class='agent-id' type='hidden' name=\"agents[" + rowId + "]['agent_id']\" value='' />");
           splitRow.push("</span>");
           splitRow.push("</td>");
           
           // rate
           splitRow.push("<td>");
           splitRow.push("<input class='agent_rate' type='text' name=\"agents[" + rowId + "]['agent_split']\" value='" + agentSplit + "' />");
           splitRow.push("</td>");
           
           // actions
           splitRow.push("<td>");
           splitRow.push("<input type='button' class='remove-row' value='Remove' />");
           splitRow.push("</td>");
           
           splitRow.push("</tr>");
           $("#affwp_add_referral .split-list tbody").append(splitRow.join(""));
           $("#affwp_add_referral .split-list tbody tr:last-child .remove-row").click(function removeSplitRow() {
               $(this).closest("tr").remove();
           });
           setupAgentSearch("#affwp_add_referral .split-list tbody tr:last-child .affwp-agent-search");
           $("#affwp_add_referral .split-list .agent_rate").change(calculateAgentSplitTotal);
    }
    function calculateAgentSplitTotal(evt) {
        var total = 0;
        $("#affwp_add_referral .split-list .agent_rate").each(function() {
            $this = $(this);
            $this.removeClass("error");
            var value = +$this.val();
            if (!isNaN(value) && value >= 0 && value <= 100) {
                total += value;
            }
            else {
                $this.addClass("error");
            }
        });
        $splitTotal = $("#affwp_add_referral .split-list .split-total");
        $splitTotal.html(total);
        if (total < 100 || total > 100) {
            $splitTotal.addClass("error");
        }
        else {
            $splitTotal.removeClass("error");
        }
    }
    
    function setupAddReferralScreen() {
        var firstTimeSplitShown = true;
        // hide the different pieces of the site based on the check value.
        $( '#affwp_add_referral #cb_split_commission' ).click(function() {
            $('#affwp_add_referral .commission_row_single').toggleClass('hidden');
            $('#affwp_add_referral .commission_row_multiple').toggleClass('hidden');
            
            // add a row with the default being zero
            if (firstTimeSplitShown) {
                firstTimeSplitShown = false;
                addSplitRow({}, 50);
                addSplitRow({}, 50);
            }
        });
        
        $( '#affwp_add_referral .split-add').click(addSplitRow);
        
        setupAgentSearch("#affwp_add_referral .commission_row_single .affwp-agent-search");
        
    }
    $(document).ready(function() {
        setupAddReferralScreen();
        
       $( '.affwp-client-search-reset').click(resetClientSearch);
       
       $( '.affwp-client-search' ).each( function() {
		var	$this    = $( this ),
			$action  = 'affwp_ltp_search_clients',
			$search  = $this.val(),
			$client_id = $( '#client_id' );

		$this.autocomplete( {
			source: ajaxurl + '?action=' + $action + '&term=' + $search,
			delay: 500,
			minLength: 2,
			position: { offset: '0, -1' },
			select: function( event, data ) {
				$client_id.val( data.item.client_id );
                                $('.readonly-description').removeClass('hidden');
                                $('#client_name')
                                        .prop("readonly", true)
                                        .val(data.item.name);
                                $('#client_street_address')
                                        .prop("readonly", true)
                                        .val(data.item.street_address);
                                $('#client_city_address')
                                        .prop("readonly", true)
                                        .val(data.item.city);
                                $('#client_zip_address')
                                        .prop("readonly", true)
                                        .val(data.item.zip);
                                $('#client_phone')
                                        .prop("readonly", true)
                                        .val(data.item.phone);
                                $('#client_email').prop("readonly", true)
                                        .val(data.item.email);
			},
			open: function() {
				$this.addClass( 'open' );
			},
			close: function() {
				$this.removeClass( 'open' );
			}
		} );

		// Unset the user_id input if the input is cleared.
		$this.on( 'keyup', function() {
			if ( ! this.value ) {
                            resetClientSearch();
			}
		} );
	} ); 
    });
})(jQuery);