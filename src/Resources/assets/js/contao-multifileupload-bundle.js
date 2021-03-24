window.Dropzone = require('dropzone');
// import 'dropzone/dist/dropzone.css';
import { utilsBundle } from '@hundh/contao-utils-bundle';
import '../scss/dropzone.scss';

// // let Dropzone = require('dropzone');
// // Disabling autoDiscover, otherwise Dropzone will try to attach twice.
Dropzone.autoDiscover = false;

class ContaoMultifileuploadBundle
{
    /**
     *
     * @param {string} str
     * @returns {string}
     */
    static rawurlencode (str)
    {
        str = (str + '');
        return encodeURIComponent(str).replace(/!/g, '%21').replace(/'/g, '%27').replace(/\(/g, '%28').replace(/\)/g, '%29').replace(/\*/g, '%2A');
    }

    /**
     *
     * @param child
     * @param parent
     * @returns {*}
     * @private
     */
    static __extends (child, parent)
    {
        for (let i in child) {
            if (child.hasOwnProperty(i)) {
                parent[i] = child[i];
            }
        }
        return parent;
    }

    /**
     *
     * @param dropzone
     * @param name
     * @returns {string|any|Element}
     * @private
     */
    static __getField (dropzone, name)
    {
        let fields = dropzone.element.querySelectorAll('input[name="' +
            (typeof name !== 'undefined' ? name + '_' : '') +
            dropzone.options.paramName + '"]');

        if (typeof fields !== 'undefined') {
            return fields[0];
        }

        return 'undefined';
    }

    /**
     *
     * @param file
     * @param action
     * @returns {boolean}
     * @private
     */
    static __registerOnClick (file, action)
    {
        if (typeof action === 'undefined') return false;
        file.previewElement.setAttribute('onclick', action);
        file.previewElement.className = '' +
            file.previewElement.className + ' has-info';
    }

    /**
     *
     * @param {string} str
     * @returns {*}
     */
    static camelize (str)
    {
        return str.replace(/[\-_](\w)/g, function (match) {
            return match.charAt(1).toUpperCase();
        });
    }

    /**
     *
     * @param dropzone
     * @param callback
     * @private
     */
    static __submitOnChange (dropzone, callback)
    {
        if (callback) {

            if (callback === 'this.form.submit()') {
                document.createElement('form').submit.call(ContaoMultifileuploadBundle.__getField(dropzone).form);
                return;
            }

            let fn = Function(callback);
            fn();
        }
    }
}

let __defaults = {
        init: function () {
            // listeners
            this.on('thumbnail', function (file, dataUrl) {
                if (file.width < this.options.minImageWidth ||
                    file.height < this.options.minImageHeight) {
                    if (typeof file.rejectDimensions === 'function')
                        file.rejectDimensions();
                } else {
                    if (typeof file.acceptDimensions === 'function')
                        file.acceptDimensions();
                }
            }).on('removedfile', function (file) {
                // remove the file from the server on form submit (store deleted files in hidden _deleted field)
                if (file.accepted) {
                    let uploaded = ContaoMultifileuploadBundle.__getField(this, 'uploaded'),
                        deleted = ContaoMultifileuploadBundle.__getField(this, 'deleted'),
                        filesToSave = ContaoMultifileuploadBundle.__getField(this);
                    if (typeof uploaded !== 'undefined' &&
                        typeof file.uuid !== 'undefined') {
                        let arrUploaded = JSON.parse(
                            uploaded.value);
                        uploaded.value = JSON.stringify(
                            utilsBundle.array.removeFromArray(
                                file.uuid,
                                arrUploaded));
                    }

                    if (typeof filesToSave !== 'undefined' &&
                        typeof file.uuid !== 'undefined') {
                        let arrFilesToSave = JSON.parse(
                            filesToSave.value);
                        filesToSave.value = JSON.stringify(
                            utilsBundle.array.removeFromArray(
                                file.uuid,
                                arrFilesToSave));
                    }

                    if (typeof deleted !== 'undefined' &&
                        typeof file.uuid !== 'undefined') {
                        let arrDeleted = JSON.parse(
                            deleted.value);
                        arrDeleted.push(file.uuid);
                        deleted.value = JSON.stringify(
                            arrDeleted);
                    }

                    // remove dz-has-files css class
                    if (this.files.length < 1) {
                        this.element.classList.remove(
                            'dz-has-files');
                    }

                    // submitOnChange support for multiple files only
                    if (this.options.maxFiles > 1) {
                        ContaoMultifileuploadBundle.__submitOnChange(this, this.options.onchange);
                    }
                }

            }).on('success', function (file, response) {

                if (typeof response.result === 'undefined') {
                    dropzone.emit('error', file,
                        dropzone.options.dictResponseError.replace(
                            '{{statusCode}}', ': Empty response'),
                        response);
                    return;
                }

                // update request token
                dropzone.options.url = utilsBundle.url.addParameterToUri(
                    dropzone.options.url, 'ato',
                    response.token);

                // each file is handled here
                response = response.result.data;

                if (response.result === 'undefined') {
                    return false;
                }

                let uploaded = ContaoMultifileuploadBundle.__getField(this, 'uploaded'),
                    filesToSave = ContaoMultifileuploadBundle.__getField(this),
                    objHandler;

                if (response instanceof Array) {
                    for (let i = 0, len = response.length; i <
                    len; i++) {
                        if ((objHandler = handleResponse(file,
                            response[i])) !== false) {
                            file = objHandler;
                            persistFile(file, uploaded,
                                filesToSave);
                            if (file.dataURL) {
                                dropzone.emit('thumbnail', file,
                                    file.dataURL);
                            }
                            ContaoMultifileuploadBundle.__registerOnClick(file, file.info);
                            break; // if file found break
                        }
                    }
                } else {
                    if ((objHandler = handleResponse(file,
                        response)) !== false) {
                        file = objHandler;
                        persistFile(file, uploaded,
                            filesToSave);
                        if (file.dataURL) {
                            dropzone.emit('thumbnail', file,
                                file.dataURL);
                        }
                        ContaoMultifileuploadBundle.__registerOnClick(file, file.info);
                    }
                }

                ContaoMultifileuploadBundle.__submitOnChange(dropzone,
                    dropzone.options.onchange);

                function persistFile(
                    file, uploaded, filesToSave) {
                    if (typeof uploaded !== 'undefined') {
                        let arrUploaded = JSON.parse(
                            uploaded.value);
                        arrUploaded.push(file.uuid);
                        uploaded.value = JSON.stringify(
                            arrUploaded);
                    }

                    if (typeof filesToSave !== 'undefined') {
                        let arrFilesToSave = JSON.parse(
                            filesToSave.value);
                        arrFilesToSave.push(file.uuid);
                        filesToSave.value = JSON.stringify(
                            arrFilesToSave);
                    }
                }

                function handleResponse(file, response) {
                    if (response.error) {
                        dropzone.emit('error', file,
                            response.error,
                            response);
                        return false;
                    }

                    // save comparison of the encoded file names
                    if (response.filenameOrigEncoded ===
                        ContaoMultifileuploadBundle.rawurlencode(file.name) &&
                        response.uuid !==
                        'undefined') {
                        file.serverFileName = response.filename;
                        file.uuid = response.uuid;
                        file.url = response.url;
                        file.info = response.info;
                        file.sanitizedName = response.filenameSanitized;

                        // do always use the sanitized filename as dropzone preview name
                        file.previewElement.querySelector(
                            '[data-dz-name]').innerHTML = response.filenameSanitized;

                        return file;
                    }

                    return false;
                }
            }).on('error', function (file, message, xhr) {

                // remove dz-error-show from other preview elements
                let siblings = file.previewElement.parentNode.querySelectorAll(
                    '.dz-error-show');

                if (siblings) {
                    for (let i = 0, len = siblings.length; i <
                    len; i++) {
                        let sibling = siblings[i];
                        sibling.classList.remove('dz-error-show');
                    }
                }

                file.previewElement.classList.remove('dz-success');
                file.previewElement.classList.add('dz-error-show');

                file.previewElement.addEventListener('mouseleave',
                    function () {
                        this.classList.remove('dz-error-show');
                    });
            }).on('sending', function (file, xhr, formData) {
                // append the whole form data
                let field = ContaoMultifileuploadBundle.__getField(this), form;

                if (typeof field !== 'undefined') {
                    form = field.form;
                }

                if (typeof form !== 'undefined') {

                    formData.append('action', this.options.uploadAction);
                    formData.append('requestToken',
                        this.options.requestToken);
                    formData.append('FORM_SUBMIT', form.id);
                    formData.append('field', this.options.paramName);

                    let inputs = form.querySelectorAll(
                        'input[name]:not([disabled]), textarea[name]:not([disabled]), select[name]:not([disabled]), button[name]:not([disabled])');

                    for (let i = 0, len = inputs.length; i < len; i++) {
                        let input = inputs[i];
                        formData.append(input.name, input.value);
                    }
                }
            }).on('addedfile', function (file) {
                if (this.files.length > 0) {
                    this.element.classList.add('dz-has-files');
                }
            });

            // add mock files
            let initialFiles = ContaoMultifileuploadBundle.__getField(this, 'formattedInitial'),
                dropzone = this;

            if (typeof initialFiles !== 'undefined') {
                initialFiles = initialFiles.value;
            }

            if (typeof initialFiles !== 'undefined' && initialFiles !==
                '') {
                let mocks = JSON.parse(initialFiles);

                for (let i = 0; i < mocks.length; i++) {
                    let mock = mocks[i];
                    mock.accepted = true;

                    this.files.push(mock);
                    this.emit('addedfile', mock);
                    if (mock.dataURL) {
                        this.emit('thumbnail', mock, mock.dataURL);
                    }
                    ContaoMultifileuploadBundle.__registerOnClick(mock, mock.info);
                    this.emit('complete', mock);
                }

                if (this.files.length > 0) {
                    this.element.classList.add('dz-has-files');
                }
            }
        },
    };

let MultiFileUpload = {
    init: function () {
        this.registerFields();
    },
    registerFields: function () {
        let fields = document.querySelectorAll('.multifileupload');

        function replaceAll(subject, search, replacement) {
            return subject.split(search).join(replacement);
        }

        for (let i = 0, len = fields.length; i < len; i++) {
            let field = fields[i];
            // do not attach Dropzone again
            if (typeof field.dropzone !== 'undefined') continue;

            let attributes = field.attributes,
                n = attributes.length,
                data = field.dataset;

            // ie 10 supports no dataset
            if (typeof data === 'undefined') {
                data = {};

                for (; n--;) {
                    if (/^data-.*/.test(attributes[n].name)) {
                        let key = ContaoMultifileuploadBundle.camelize(
                            attributes[n].name.replace('data-', ''));
                        data[key] = attributes[n].value;
                    }
                }
            }

            let localizations = ['dictFileTooBig', 'dictResponseError'];

            for (let j = 0; j < localizations.length; j++) {
                data[localizations[j]] = replaceAll(
                    data[localizations[j]], '{.{', '{{');
                data[localizations[j]] = replaceAll(
                    data[localizations[j]], '}.}', '}}');
            }

            let config = ContaoMultifileuploadBundle.__extends(data, __defaults);

            config.url = location.href;

            if (utilsBundle.util.isTruthy(history.state) &&
                utilsBundle.util.isTruthy(history.state.url)) {
                config.url = history.state.url;
            }

            if (config.uploadActionParams) {
                let params = utilsBundle.url.parseQueryString(
                    config.uploadActionParams);

                config.url = utilsBundle.url.addParametersToUri(
                    config.url,
                    params);
            }

            new Dropzone(field, config);
        }

    },
}
;

document.addEventListener('DOMContentLoaded', function () {
    MultiFileUpload.init();

    // jquery support
    if (window.jQuery) {
        window.jQuery(document).ajaxComplete(function () {
            MultiFileUpload.init();
        });
    }

    // mootools support
    if (window.MooTools) {
        window.addEvent('ajax_change', function () {
            MultiFileUpload.init();
        });
    }

    document.addEventListener('formhybrid_ajax_complete', function () {
        MultiFileUpload.init();
    });
});