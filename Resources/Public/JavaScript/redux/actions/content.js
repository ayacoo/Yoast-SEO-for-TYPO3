export const GET_CONTENT_REQUEST = 'GET_CONTENT_REQUEST';
export const GET_CONTENT_SUCCESS = 'GET_CONTENT_SUCCESS';
export const GET_CONTENT_ERROR = 'GET_CONTENT_ERROR';

export function getContent() {
    return dispatch => {
        dispatch({type: GET_CONTENT_REQUEST});

        return fetch(tx_yoast_seo.settings.preview)
            .then(response => {
                return response.json();
            })
            .then(data => {
                if (!data.description) {
                    const bodyText = document.createElement('div');
                    bodyText.innerHTML = data.body;
                    data.description = bodyText.innerText;
                }

                dispatch({type: GET_CONTENT_SUCCESS, payload: data});
            })
            .catch(error => {
                dispatch({type: GET_CONTENT_ERROR, payload: error, error: true});
            });
    };
}
