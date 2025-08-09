// Cookie functions from http://www.quirksmode.org/js/cookies.html //

function startsWith(haystack, needle) {
    return haystack.slice(0, needle.length) == needle;
}

function createCookie(name, value, days, path) {
    var getLocation = function (href) {
        var l = document.createElement("a");
        l.href = href;
        return l;
    };

    if (!path) {
        path = '/';
    } else {
        var location = getLocation(path);
        path = location.pathname;
        if (!startsWith(path, '/')) {
            path = '/' + path;
        }
    }
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        var expires = "; expires=" + date.toGMTString();
    } else {
        var expires = "";
    }
    document.cookie = name + "=" + value + expires + "; path=" + path;
}

function readCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) == ' ') {
            c = c.substring(1, c.length);
        }
        if (c.indexOf(nameEQ) == 0) {
            return c.substring(nameEQ.length, c.length);
        }
    }
    return null;
}

function eraseCookie(name, path) {
    createCookie(name, '', -30, path);
}

function getQueryStringValue(name) {
    name = name.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]");
    var regexS = "[\\?&]" + name + "=([^&#]*)";
    var regex = new RegExp(regexS);
    var results = regex.exec(window.location.href);
    if (results == null) {
        return '';
    } else {
        return decodeURIComponent(results[1].replace(/\+/g, " "));
    }

}

// Initialize Bootstrap tooltips
function initializeBootstrapTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

function init() {

}

function validateEmail(email) {
    var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(email);
}

var cookies = {

    // cookieName: 'dismissed',

    isDismissed: function (id) {
        var dismissed = readCookie('dismissed');

        if (!dismissed) {
            return false;
        }

        var idsDismissed = dismissed.split(',');

        return idsDismissed.indexOf(id) !== -1;
    },

    dismiss: function (id, path) {
        var dismissed = readCookie('dismissed');

        if (!dismissed) {
            dismissed = [];
        } else {
            dismissed = dismissed.split(',');
        }
        if (dismissed.indexOf(id) === -1) {
            dismissed.push(id);
        }
        createCookie('dismissed', dismissed, 30, path);
    }
};

document.addEventListener("DOMContentLoaded", function () {
    var buttonUp = document.getElementById("button-up");

    buttonUp.addEventListener("click", scrollUp);

    function scrollUp() {
        var currentScroll = document.documentElement.scrollTop;
        if (currentScroll > 0) {
            window.scrollTo(0, currentScroll - (currentScroll / 1));
        }
    }

    window.onscroll = function () {
        var scroll = document.documentElement.scrollTop;
        if (scroll > 500) {
            buttonUp.style.transform = "scale(1)";
        } else if (scroll < 500) {
            buttonUp.style.transform = "scale(0)";
        }
    };
});

function initializeAccordions() {
    // Get all elements with the class 'accordion-item' (representing the accordions)
    var accordionItems = document.querySelectorAll('.accordion-item');
    // Create an array to store the IDs of the accordions
    var accordionIds = [];
    // Iterate over each 'accordion-item' element
    accordionItems.forEach(function (item) {
        // Get the ID of each accordion and add it to the array
        var accordionId = item.getAttribute('id');
        accordionIds.push(accordionId);
    });

    // Iterate over each accordion
    accordionIds.forEach(function (accordionId) {
        var accordionState = localStorage.getItem(
            accordionId); // Get the accordion state from localStorage
        if (accordionState === 'collapsed') {
            $('#' + accordionId + ' .accordion-collapse').collapse(
                'hide'); // Collapse the accordion if it's saved as "collapsed"
        } else {
            $('#' + accordionId + ' .accordion-collapse').collapse(
                'show'); // Expand the accordion if it's saved as "expanded"
        }
    });
}

// Function to save the state of accordions when they are collapsed or expanded
$('.accordion-collapse').on('hidden.bs.collapse', function () {
    var accordionId = $(this).closest('.accordion-item').attr(
        'id'); // Get the unique identifier of the accordion
    localStorage.setItem(accordionId, 'collapsed'); // Save the "collapsed" state in localStorage
});

$('.accordion-collapse').on('shown.bs.collapse', function () {
    var accordionId = $(this).closest('.accordion-item').attr(
        'id'); // Get the unique identifier of the accordion
    localStorage.setItem(accordionId, 'expanded'); // Save the "expanded" state in localStorage
});

// Call the function to initialize the accordions when the page is fully loaded
document.addEventListener('DOMContentLoaded', function () {
    initializeAccordions();
    initializeBootstrapTooltips();
});