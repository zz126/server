import Vue from 'vue'

import AdminTwoFactor from './components/AdminTwoFactor.vue'
import store from './store/admin-security'

__webpack_nonce__ = btoa(OC.requestToken)

Vue.prototype.t = t;

const initialStateElem = document.getElementById('two-factor-auth-settings-initial-state');
store.replaceState(
	JSON.parse(atob(initialStateElem.value))
)

const View = Vue.extend(AdminTwoFactor)
new View({
	store
}).$mount('#two-factor-auth-settings')
