import Vue from 'vue';
import PersonalSettings from './views/PersonalSettings';
import store from './store';

Vue.prototype.t = t;

const initialStateElem = document.getElementById('initial-state-twofactor_backupcodes');
store.replaceState(
	JSON.parse(atob(initialStateElem.value))
)

const View = Vue.extend(PersonalSettings)
new View({
	store
}).$mount('#twofactor-backupcodes-settings')
