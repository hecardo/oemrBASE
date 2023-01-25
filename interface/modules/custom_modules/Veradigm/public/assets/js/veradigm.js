'use strict';

// TODO: Add code to submit facilities to Veradigm
// TODO: Add code to submit users to Veradigm
// TODO: Add code to sync patients with Veradigm
// TODO: Add code to access the Veradigm interface


/* CONSTANTS */


const DEFAULT_API_HEADERS = {
	'accept': 'application/json;q=0.9,application/vnd.api+json;q=0.7,text/javascript;q=0.5,*/*;q=0.1',
	'accept-language': 'en-US,en;q=0.8',
	'cache-control': 'no-cache',
	'content-type': 'application/json',
	'pragma': 'no-cache'
};

const CACHING_API_HEADERS = {
	'accept': 'application/json;q=0.9,application/vnd.api+json;q=0.7,text/javascript;q=0.5,*/*;q=0.1',
	'accept-language': 'en-US,en;q=0.8',
	'cache-control': 'max-age=3600',
	'content-type': 'application/json'
};


/* AJAX FUNCTIONS */


function api_get_json(url, data_func, cache = false) {
	const headers = cache ? CACHING_API_HEADERS : DEFAULT_API_HEADERS;
	$.ajax({
		cache: cache,
		contentType: 'application/json',
		crossDomain: false,
		dataType: 'json',
		headers: headers,
		method: 'GET',
		url: url
	})
	.done((resp) => { return data_func(resp); })
	.fail((request, status, err_msg) => {
		console.error('AJAX ERROR (Status: ' + status + '): ' + err_msg);
		return {};
	});
}

function api_post_json(url, data, data_func) {
	var xhr = new XMLHttpRequest();
	xhr.open('POST', url);
	xhr.timeout = 2000;
	xhr.setRequestHeader('accept', 'application/json;q=0.9,application/vnd.api+json;q=0.7,text/javascript;q=0.5,*/*;q=0.1');
	xhr.setRequestHeader('accept-language', 'en-US,en;q=0.8');
	xhr.setRequestHeader('cache-control', 'no-cache');
	xhr.setRequestHeader('content-type', 'application/json');
	xhr.setRequestHeader('pragma', 'no-cache');
	xhr.onreadystatechange = function () {
		if (xhr.readyState === 4) {
			if (xhr.status === 200) {
				return data_func(xhr.responseText);
			} else if (xhr.status >= 400) {
				console.error('AJAX ERROR (Status: ' + xhr.status + ')');
				return {};
			}
		}
	};
	xhr.send(JSON.stringify(data));
}

function post_saml(url, saml_data, data_func) {
	var xhr = new XMLHttpRequest();
	xhr.open('POST', url);
	xhr.timeout = 2000;
	xhr.setRequestHeader('accept', '*/*;q=0.1');
	xhr.setRequestHeader('accept-language', 'en-US,en;q=0.8');
	xhr.setRequestHeader('cache-control', 'no-cache');
	xhr.setRequestHeader('content-type', 'application/x-www-form-urlencoded');
	xhr.setRequestHeader('pragma', 'no-cache');
	xhr.onreadystatechange = function () {
		if (xhr.readyState === 4) {
			if (xhr.status === 200) {
				return data_func();
			} else if (xhr.status >= 400) {
				console.error('AJAX ERROR (Status: ' + xhr.status + ')');
				return {};
			}
		}
	};
	xhr.send('SAMLResponse=' + saml_data);
}


/* HELPER FUNCTIONS */


/** Provide a save-as dialog for the URI */
function saveAs(uri, title='Download File') {
	let _link = document.createElement('a');
	if (typeof _link.download === 'string') {
		_link.href = uri;
		_link.setAttribute('download', _title);
		document.body.appendChild(_link);
		_link.click();
		document.body.removeChild(_link);
	} else {
		window.open(uri, '_blank');
	}
}


/** Produce a UUIDv4 string */
function uuidv4() {
	return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => { const r = ((Math.random() * 16) | 0); return (c == 'x' ? r : (r & 3 | 8)).toString(16); });
}


/* ICE FUNCTIONS */


function get_pid() {
	let elem = document.querySelector('div#attendantData span[data-bind="text: pubpid"]');
	if (elem) {
		return elem.innerText;
	}
	return 0;
}

function veradigm_attach_patient_listeners() {
	let tmp_elem = document.getElementById('mod_veradigm_patient_lockdown');
	if (tmp_elem) {
		tmp_elem.addEventListener('click', veradigm_interface);
	}
	tmp_elem = document.getElementById('mod_veradigm_patient_context');
	if (tmp_elem) {
		tmp_elem.addEventListener('click', veradigm_interface);
	}
}

function veradigm_attach_listeners() {
	let tmp_elem = document.getElementById('mod_veradigm_utility');
	if (tmp_elem) {
		tmp_elem.addEventListener('click', veradigm_interface);
	}
	tmp_elem = document.getElementById('mod_veradigm_task');
	if (tmp_elem) {
		tmp_elem.addEventListener('click', veradigm_interface);
	}
	tmp_elem = document.getElementById('mod_veradigm_standard_sso');
	if (tmp_elem) {
		tmp_elem.addEventListener('click', veradigm_interface);
	}
}

function veradigm_interface(evt) {
	let elem = evt.srcElement;
	const mode = elem.id;
	elem.disabled = true;
	const saml_url = '/api/veradigm/mode/' + mode;
	var fetch_saml_func = function (resp) {
		const json_data = JSON.parse(resp);
		let new_window = window.open(null, '', '_blank');
		let js_elem = new_window.document.createElement('script');
		js_elem.type = 'text/javascript';
		js_elem.src = json_data.script;
		let script_elem = new_window.document.createElement('script');
		script_elem.type = 'text/javascript';
		script_elem.innerHTML = 'post_saml("' + json_data.redirect_url + '", "' + json_data.saml + '", function () {})';
		elem.disabled = false;
		new_window.document.body.appendChild(js_elem);
		new_window.document.body.appendChild(script_elem);
	};
	api_get_json(saml_url, fetch_saml_func);
}
