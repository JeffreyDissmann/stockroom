import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\ImageSearchController::fromSearch
* @see app/Http/Controllers/ImageSearchController.php:57
* @route '/items/{item}/images/from-search'
*/
export const fromSearch = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: fromSearch.url(args, options),
    method: 'post',
})

fromSearch.definition = {
    methods: ["post"],
    url: '/items/{item}/images/from-search',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ImageSearchController::fromSearch
* @see app/Http/Controllers/ImageSearchController.php:57
* @route '/items/{item}/images/from-search'
*/
fromSearch.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return fromSearch.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ImageSearchController::fromSearch
* @see app/Http/Controllers/ImageSearchController.php:57
* @route '/items/{item}/images/from-search'
*/
fromSearch.post = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: fromSearch.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ItemImageController::store
* @see app/Http/Controllers/ItemImageController.php:21
* @route '/items/{item}/images'
*/
export const store = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/items/{item}/images',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ItemImageController::store
* @see app/Http/Controllers/ItemImageController.php:21
* @route '/items/{item}/images'
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
* @see \App\Http\Controllers\ItemImageController::store
* @see app/Http/Controllers/ItemImageController.php:21
* @route '/items/{item}/images'
*/
store.post = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ItemImageController::reorder
* @see app/Http/Controllers/ItemImageController.php:64
* @route '/items/{item}/images/order'
*/
export const reorder = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reorder.url(args, options),
    method: 'patch',
})

reorder.definition = {
    methods: ["patch"],
    url: '/items/{item}/images/order',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\ItemImageController::reorder
* @see app/Http/Controllers/ItemImageController.php:64
* @route '/items/{item}/images/order'
*/
reorder.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return reorder.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ItemImageController::reorder
* @see app/Http/Controllers/ItemImageController.php:64
* @route '/items/{item}/images/order'
*/
reorder.patch = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reorder.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\ItemImageController::update
* @see app/Http/Controllers/ItemImageController.php:34
* @route '/items/{item}/images/{image}'
*/
export const update = (args: { item: number | { id: number }, image: number | { id: number } } | [item: number | { id: number }, image: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/items/{item}/images/{image}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\ItemImageController::update
* @see app/Http/Controllers/ItemImageController.php:34
* @route '/items/{item}/images/{image}'
*/
update.url = (args: { item: number | { id: number }, image: number | { id: number } } | [item: number | { id: number }, image: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            item: args[0],
            image: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        item: typeof args.item === 'object'
        ? args.item.id
        : args.item,
        image: typeof args.image === 'object'
        ? args.image.id
        : args.image,
    }

    return update.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace('{image}', parsedArgs.image.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ItemImageController::update
* @see app/Http/Controllers/ItemImageController.php:34
* @route '/items/{item}/images/{image}'
*/
update.patch = (args: { item: number | { id: number }, image: number | { id: number } } | [item: number | { id: number }, image: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\ItemImageController::destroy
* @see app/Http/Controllers/ItemImageController.php:48
* @route '/items/{item}/images/{image}'
*/
export const destroy = (args: { item: number | { id: number }, image: number | { id: number } } | [item: number | { id: number }, image: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/items/{item}/images/{image}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\ItemImageController::destroy
* @see app/Http/Controllers/ItemImageController.php:48
* @route '/items/{item}/images/{image}'
*/
destroy.url = (args: { item: number | { id: number }, image: number | { id: number } } | [item: number | { id: number }, image: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            item: args[0],
            image: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        item: typeof args.item === 'object'
        ? args.item.id
        : args.item,
        image: typeof args.image === 'object'
        ? args.image.id
        : args.image,
    }

    return destroy.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace('{image}', parsedArgs.image.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ItemImageController::destroy
* @see app/Http/Controllers/ItemImageController.php:48
* @route '/items/{item}/images/{image}'
*/
destroy.delete = (args: { item: number | { id: number }, image: number | { id: number } } | [item: number | { id: number }, image: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const images = {
    fromSearch: Object.assign(fromSearch, fromSearch),
    store: Object.assign(store, store),
    reorder: Object.assign(reorder, reorder),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default images