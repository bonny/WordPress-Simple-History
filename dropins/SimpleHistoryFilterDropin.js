/**
 *
 */

var SimpleHistoryFilterDropin = (function ($) {
  var $elms = {}
  var isFilteringActive = false
  var activeFilters = {}

  function init () {
    addElements()
    addFetchListener()
  }

  function onDomReadyInit () {
    enhanceSelects()
    addListeners()
  }

  function addElements () {
    $elms.filter_container = $('.SimpleHistory__filters')
    $elms.filter_user = $elms.filter_container.find(
      '.SimpleHistory__filters__filter--user'
    )
    $elms.filter_button = $elms.filter_container.find(
      '.js-SimpleHistoryFilterDropin-doFilter'
    )
    $elms.filter_form = $elms.filter_container.find(
      '.js-SimpleHistory__filters__form'
    )
    $elms.show_more_filters_button = $elms.filter_container.find(
      '.js-SimpleHistoryFilterDropin-showMoreFilters'
    )
    $elms.more_filters_container = $elms.filter_container.find(
      '.js-SimpleHistory__filters__moreFilters'
    )
  }

  function addListeners () {
    $elms.filter_form.on('submit', onSubmitForm)
    $elms.show_more_filters_button.on('click', onClickMoreFilters)
  }

  function onClickMoreFilters () {
    // $elms.more_filters_container.toggleClass("is-visible");
    $elms.filter_container.toggleClass('is-showingMoreFilters')
  }

  function updateFilters () {
    // form serialize
    // search=apa&loglevels=critical&loglevels=alert&loggers=SimpleMediaLogger&loggers=SimpleMenuLogger&user=1&dates=2014-09 SimpleHistoryFilterDropin.js?ver=2.0:40
    var $search = $elms.filter_form.find("[name='search']")
    var $loglevels = $elms.filter_form.find("[name='loglevels']")
    var $messages = $elms.filter_form.find("[name='messages']")
    var $users = $elms.filter_form.find("[name='users']")
    var $dates = $elms.filter_form.find("[name='dates']")

    // Custom date range
    var $customDateRangeFromMM = $elms.filter_form.find("[name='from_mm']")
    var $customDateRangeFromJJ = $elms.filter_form.find("[name='from_jj']")
    var $customDateRangeFromAA = $elms.filter_form.find("[name='from_aa']")
    var $customDateRangeToMM = $elms.filter_form.find("[name='to_mm']")
    var $customDateRangeToJJ = $elms.filter_form.find("[name='to_jj']")
    var $customDateRangeToAA = $elms.filter_form.find("[name='to_aa']")

    // If any of our search boxes are filled in we consider ourself to be in search mode
    isFilteringActive = false
    activeFilters = {}

    if ($.trim($search.val())) {
      isFilteringActive = true
      activeFilters.search = $search.val()
    }

    if ($loglevels.val() && $loglevels.val().length) {
      isFilteringActive = true
      activeFilters.loglevels = $loglevels.val()
    }

    if ($messages.val() && $messages.val().length) {
      isFilteringActive = true
      activeFilters.messages = $messages.val()
    }

    if ($.trim($users.val())) {
      isFilteringActive = true
      activeFilters.users = $users.val()
    }

    // Something is selected in the Dates dropdown
    if ($dates.val() && $dates.val().length) {
      isFilteringActive = true

      // if dates val is selected but is "customRange" then dates is not active, but dateRange is
      if ($dates.val() == 'customRange') {
        activeFilters.date_from =
          $customDateRangeFromAA.val() +
          '-' +
          $customDateRangeFromMM.val() +
          '-' +
          $customDateRangeFromJJ.val() +
          ' 00:00:00'
        activeFilters.date_to =
          $customDateRangeToAA.val() +
          '-' +
          $customDateRangeToMM.val() +
          '-' +
          $customDateRangeToJJ.val() +
          ' 23:59:59'
      } else {
        activeFilters.dates = $dates.val()
      }
    }
  }

  function onSubmitForm (e) {
    e.preventDefault()

    // updateFilters();

    // Reload the log rows collection
    simple_history.logRowsCollection.reload()
  }

  function addFetchListener () {
    $(document).on('SimpleHistory:mainViewInitBeforeLoadRows', function () {
      // Modify query string parameters before the log rows collection fetches/syncs
      simple_history.logRowsCollection.on('before_fetch', modifyFetchData)
    })

    // Alter api args used by new log rows notifier
    $(document).on(
      'SimpleHistory:NewRowsNotifier:apiArgs',
      modifyNewRowsNotifierApiArgs
    )
  }

  function modifyNewRowsNotifierApiArgs (e, apiArgs) {
    if (isFilteringActive) {
      apiArgs = _.extend(apiArgs, activeFilters)
    }
  }

  // called each time the log is reloaded
  function modifyFetchData (collection, url_data) {
    updateFilters()

    if (isFilteringActive) {
      url_data = _.extend(url_data, activeFilters)
    }
  }

  function enhanceSelects () {
    $elms.filter_user.select2({
      minimumInputLength: 2,
      allowClear: true,
      placeholder: 'All users',
      ajax: {
        url: ajaxurl,
        dataType: 'json',
        cache: true,
        data: function (term, page) {
          return {
            q: term.term, // search term
            page_limit: 10,
            action: 'simple_history_filters_search_user'
          }
        },
        // parse the results into the format expected by Select2.
        processResults: function (data, page) {
          // since we are using custom formatting functions we do not need to alter remote JSON data
          return data.data
        }
      },
      templateResult: formatUsers,
      initSelection: function (elm, callback) {
        // called on init if value attribute on input is set
        var $elm = $(elm)
        var value = $elm.val()
        var default_user_data = $elms.filter_user.data('default-user-data')

        callback(default_user_data)
      },
      templateSelection: formatUsers,
      escapeMarkup: function (m) {
        return m
      },
      multiple: true
    })

    $('.SimpleHistory__filters__filter--logger').select2()

    var $filterDate = $('.SimpleHistory__filters__filter--date')
    $filterDate.select2()
    $filterDate.on('select2-selecting change', onDatesFilterSelect)

    $('.SimpleHistory__filters__filter--loglevel').select2({
      templateResult: formatLoglevel,
      templateSelection: formatLoglevel,
      escapeMarkup: function (m) {
        return m
      }
    })
  }

  /**
   * Fired when something is selected in the date filter
   * When "Custom range..." is selected then we show the "from" .. "to" date fields
   */
  function onDatesFilterSelect (e, elm) {
    var $filterDate = $('select.SimpleHistory__filters__filter--date')
    var val = $filterDate.val()

    if (val === 'customRange') {
      // show custom date fields
      $elms.filter_container.addClass('is-customDateFilterActive')
    } else {
      // hide custom date fields
      $elms.filter_container.removeClass('is-customDateFilterActive')
    }
  }

  function formatUsers (userdata) {
    if (userdata.loading) {
      return userdata.text
    }

    var html = ''

    html += "<div class='SimpleHistory__filters__userfilter__gravatar'>"
    html += userdata.gravatar
    html += '</div>'
    html += "<div class='SimpleHistory__filters__userfilter__primary'>"
    html += userdata.user_email
    html += '</div>'
    html += "<div class='SimpleHistory__filters__userfilter__secondary'>"
    html += userdata.user_login
    html += '</div>'

    return html
  }

  /**
   * Used by templateResult and templateSelection
   */
  function formatLoglevel (loglevel) {
    var originalOption = loglevel.element
    var $originalOption = $(originalOption)
    var color = $originalOption.data('color')

    var html =
      '<span style="border-radius: 50%; border: 1px solid rgba(0,0,0,.1); margin-right: 5px; width: .75em; height: .75em; line-height: 1; display: inline-block; background-color: ' +
      $originalOption.data('color') +
      "; '\"></span>" +
      loglevel.text
    return html
  }

  return {
    init: init,
    onDomReadyInit: onDomReadyInit,
    $elms: $elms
  }
})(jQuery)

SimpleHistoryFilterDropin.init()

jQuery(document).ready(function () {
  SimpleHistoryFilterDropin.onDomReadyInit()
})
