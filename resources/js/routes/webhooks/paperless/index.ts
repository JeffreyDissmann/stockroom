import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\PaperlessWebhookController::document
* @see app/Http/Controllers/PaperlessWebhookController.php:22
* @route '/webhooks/paperless/document'
*/
export const document = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: document.url(options),
    method: 'post',
})

document.definition = {
    methods: ["post"],
    url: '/webhooks/paperless/document',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PaperlessWebhookController::document
* @see app/Http/Controllers/PaperlessWebhookController.php:22
* @route '/webhooks/paperless/document'
*/
document.url = (options?: RouteQueryOptions) => {
    return document.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PaperlessWebhookController::document
* @see app/Http/Controllers/PaperlessWebhookController.php:22
* @route '/webhooks/paperless/document'
*/
document.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: document.url(options),
    method: 'post',
})

const paperless = {
    document: Object.assign(document, document),
}

export default paperless