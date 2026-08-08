/**
 * Starts a carousel that Elementor has just placed in the page.
 *
 * The carousel starts itself on DOMContentLoaded, which is enough for a plain
 * page. Elementor injects widgets after that: on the front end when a section
 * loads late, and in the editor on every change to a widget's settings.
 */
(function (window, document) {
    'use strict';

    /* Every scene we have started, so the ones Elementor throws away can be
       cleaned up. Editing a setting re-renders the widget, and without this the
       discarded copy keeps its resize listener and its animation loop. */
    var started = [];

    function sweep() {
        started = started.filter(function (scene) {
            if (document.body.contains(scene)) {
                return true;
            }
            /* Detached, so querySelectorAll would never reach it — the instance
               hanging off the element is the only way back to it. */
            if (scene._c3d && scene._c3d.destroy) {
                scene._c3d.destroy();
            }
            return false;
        });
    }

    function ready($scope) {
        if (!window.C3D || !window.C3D.init) {
            return;
        }

        sweep();

        var root = $scope && $scope[0] ? $scope[0] : $scope;
        if (!root || typeof root.querySelectorAll !== 'function') {
            return;
        }

        window.C3D.init(root);

        Array.prototype.forEach.call(
            root.querySelectorAll('.c3d-scene'),
            function (scene) {
                if (started.indexOf(scene) === -1) {
                    started.push(scene);
                }
            }
        );
    }

    window.addEventListener('elementor/frontend/init', function () {
        window.elementorFrontend.hooks.addAction(
            'frontend/element_ready/carousel_3d.default',
            ready
        );

        /* Also cover Elementor's shortcode widget, for pages that mix the two.
           This script only loads when the carousel widget is on the page, so a
           page built entirely from shortcode widgets does not get it — there the
           carousel starts normally on the front end and needs a reload in the
           editor. */
        window.elementorFrontend.hooks.addAction(
            'frontend/element_ready/shortcode.default',
            ready
        );
    });
})(window, document);
