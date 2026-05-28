import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\ImageSearchController::search
* @see app/Http/Controllers/ImageSearchController.php:34
* @route '/items/{item}/image-search'
*/
export const search = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: search.url(args, options),
    method: 'get',
})

search.definition = {
    methods: ["get","head"],
    url: '/items/{item}/image-search',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ImageSearchController::search
* @see app/Http/Controllers/ImageSearchController.php:34
* @route '/items/{item}/image-search'
*/
search.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return search.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ImageSearchController::search
* @see app/Http/Controllers/ImageSearchController.php:34
* @route '/items/{item}/image-search'
*/
search.get = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: search.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ImageSearchController::search
* @see app/Http/Controllers/ImageSearchController.php:34
* @route '/items/{item}/image-search'
*/
search.head = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: search.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ImageSearchController::attach
* @see app/Http/Controllers/ImageSearchController.php:57
* @route '/items/{item}/images/from-search'
*/
export const attach = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: attach.url(args, options),
    method: 'post',
})

attach.definition = {
    methods: ["post"],
    url: '/items/{item}/images/from-search',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ImageSearchController::attach
* @see app/Http/Controllers/ImageSearchController.php:57
* @route '/items/{item}/images/from-search'
*/
attach.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return attach.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ImageSearchController::attach
* @see app/Http/Controllers/ImageSearchController.php:57
* @route '/items/{item}/images/from-search'
*/
attach.post = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: attach.url(args, options),
    method: 'post',
})

const ImageSearchController = { search, attach }

export default ImageSearchController