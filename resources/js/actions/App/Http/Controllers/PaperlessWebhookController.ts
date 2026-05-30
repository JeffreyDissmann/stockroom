import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PaperlessWebhookController::store
* @see app/Http/Controllers/PaperlessWebhookController.php:22
* @route '/webhooks/paperless/document'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/webhooks/paperless/document',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\PaperlessWebhookController::store
* @see app/Http/Controllers/PaperlessWebhookController.php:22
* @route '/webhooks/paperless/document'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\PaperlessWebhookController::store
* @see app/Http/Controllers/PaperlessWebhookController.php:22
* @route '/webhooks/paperless/document'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

const PaperlessWebhookController = { store }

export default PaperlessWebhookController