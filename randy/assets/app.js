/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.css';

// start the Stimulus application
import './bootstrap';

// assets/js/app.js
import Vue from 'vue';
import App from './components/App'

import VEmojiPicker from "v-emoji-picker";

Vue.use(VEmojiPicker)

/**
* Create a fresh Vue Application instance
*/
new Vue({
    el: '#app',
    components: {App}
});