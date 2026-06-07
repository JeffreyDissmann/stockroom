import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Items\PaperlessLinkController::store
* @see app/Http/Controllers/Items/PaperlessLinkController.php:60
* @route '/items/{item}/paperless-links'
*/
export const store = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/items/{item}/paperless-links',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Items\PaperlessLinkController::store
* @see app/Http/Controllers/Items/PaperlessLinkController.php:60
* @route '/items/{item}/paperless-links'
*/
store.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { item: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { item: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            item: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        item: typeof args.item === 'object'
        ? args.item.id
        : args.item,
    }

    return store.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Items\PaperlessLinkController::store
* @see app/Http/Controllers/Items/PaperlessLinkController.php:60
* @route '/items/{item}/paperless-links'
*/
store.post = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Items\PaperlessLinkController::destroy
* @see app/Http/Controllers/Items/PaperlessLinkController.php:73
* @route '/items/{item}/paperless-links/{document}'
*/
export const destroy = (args: { item: number | { id: number }, document: string | number } | [item: number | { id: number }, document: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/items/{item}/paperless-links/{document}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Items\PaperlessLinkController::destroy
* @see app/Http/Controllers/Items/PaperlessLinkController.php:73
* @route '/items/{item}/paperless-links/{document}'
*/
destroy.url = (args: { item: number | { id: number }, document: string | number } | [item: number | { id: number }, document: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            item: args[0],
            document: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        item: typeof args.item === 'object'
        ? args.item.id
        : args.item,
        document: args.document,
    }

    return destroy.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace('{document}', parsedArgs.document.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Items\PaperlessLinkController::destroy
* @see app/Http/Controllers/Items/PaperlessLinkController.php:73
* @route '/items/{item}/paperless-links/{document}'
*/
destroy.delete = (args: { item: number | { id: number }, document: string | number } | [item: number | { id: number }, document: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const paperlessLinks = {
    store: Object.assign(store, store),
    destroy: Object.assign(destroy, destroy),
}

export default paperlessLinks