import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
import images from './images'
import box from './box'
import relatedItems from './related-items'
/**
* @see \App\Http\Controllers\ItemController::moveTargets
* @see app/Http/Controllers/ItemController.php:129
* @route '/items/{item}/move-targets'
*/
export const moveTargets = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: moveTargets.url(args, options),
    method: 'get',
})

moveTargets.definition = {
    methods: ["get","head"],
    url: '/items/{item}/move-targets',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ItemController::moveTargets
* @see app/Http/Controllers/ItemController.php:129
* @route '/items/{item}/move-targets'
*/
moveTargets.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return moveTargets.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ItemController::moveTargets
* @see app/Http/Controllers/ItemController.php:129
* @route '/items/{item}/move-targets'
*/
moveTargets.get = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: moveTargets.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ItemController::moveTargets
* @see app/Http/Controllers/ItemController.php:129
* @route '/items/{item}/move-targets'
*/
moveTargets.head = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: moveTargets.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ItemController::move
* @see app/Http/Controllers/ItemController.php:250
* @route '/items/{item}/move'
*/
export const move = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: move.url(args, options),
    method: 'patch',
})

move.definition = {
    methods: ["patch"],
    url: '/items/{item}/move',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\ItemController::move
* @see app/Http/Controllers/ItemController.php:250
* @route '/items/{item}/move'
*/
move.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return move.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ItemController::move
* @see app/Http/Controllers/ItemController.php:250
* @route '/items/{item}/move'
*/
move.patch = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: move.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\ItemPhotoAnalysisController::__invoke
* @see app/Http/Controllers/ItemPhotoAnalysisController.php:41
* @route '/items/analyze-photo'
*/
export const analyzePhoto = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: analyzePhoto.url(options),
    method: 'post',
})

analyzePhoto.definition = {
    methods: ["post"],
    url: '/items/analyze-photo',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ItemPhotoAnalysisController::__invoke
* @see app/Http/Controllers/ItemPhotoAnalysisController.php:41
* @route '/items/analyze-photo'
*/
analyzePhoto.url = (options?: RouteQueryOptions) => {
    return analyzePhoto.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ItemPhotoAnalysisController::__invoke
* @see app/Http/Controllers/ItemPhotoAnalysisController.php:41
* @route '/items/analyze-photo'
*/
analyzePhoto.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: analyzePhoto.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ItemController::index
* @see app/Http/Controllers/ItemController.php:34
* @route '/items'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/items',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ItemController::index
* @see app/Http/Controllers/ItemController.php:34
* @route '/items'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ItemController::index
* @see app/Http/Controllers/ItemController.php:34
* @route '/items'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ItemController::index
* @see app/Http/Controllers/ItemController.php:34
* @route '/items'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ItemController::create
* @see app/Http/Controllers/ItemController.php:60
* @route '/items/create'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/items/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ItemController::create
* @see app/Http/Controllers/ItemController.php:60
* @route '/items/create'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ItemController::create
* @see app/Http/Controllers/ItemController.php:60
* @route '/items/create'
*/
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ItemController::create
* @see app/Http/Controllers/ItemController.php:60
* @route '/items/create'
*/
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ItemController::store
* @see app/Http/Controllers/ItemController.php:74
* @route '/items'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/items',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\ItemController::store
* @see app/Http/Controllers/ItemController.php:74
* @route '/items'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\ItemController::store
* @see app/Http/Controllers/ItemController.php:74
* @route '/items'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\ItemController::show
* @see app/Http/Controllers/ItemController.php:95
* @route '/items/{item}'
*/
export const show = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/items/{item}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ItemController::show
* @see app/Http/Controllers/ItemController.php:95
* @route '/items/{item}'
*/
show.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ItemController::show
* @see app/Http/Controllers/ItemController.php:95
* @route '/items/{item}'
*/
show.get = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ItemController::show
* @see app/Http/Controllers/ItemController.php:95
* @route '/items/{item}'
*/
show.head = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ItemController::edit
* @see app/Http/Controllers/ItemController.php:213
* @route '/items/{item}/edit'
*/
export const edit = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

edit.definition = {
    methods: ["get","head"],
    url: '/items/{item}/edit',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ItemController::edit
* @see app/Http/Controllers/ItemController.php:213
* @route '/items/{item}/edit'
*/
edit.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return edit.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ItemController::edit
* @see app/Http/Controllers/ItemController.php:213
* @route '/items/{item}/edit'
*/
edit.get = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: edit.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ItemController::edit
* @see app/Http/Controllers/ItemController.php:213
* @route '/items/{item}/edit'
*/
edit.head = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: edit.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ItemController::update
* @see app/Http/Controllers/ItemController.php:225
* @route '/items/{item}'
*/
export const update = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put","patch"],
    url: '/items/{item}',
} satisfies RouteDefinition<["put","patch"]>

/**
* @see \App\Http\Controllers\ItemController::update
* @see app/Http/Controllers/ItemController.php:225
* @route '/items/{item}'
*/
update.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ItemController::update
* @see app/Http/Controllers/ItemController.php:225
* @route '/items/{item}'
*/
update.put = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\ItemController::update
* @see app/Http/Controllers/ItemController.php:225
* @route '/items/{item}'
*/
update.patch = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\ItemController::destroy
* @see app/Http/Controllers/ItemController.php:240
* @route '/items/{item}'
*/
export const destroy = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/items/{item}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\ItemController::destroy
* @see app/Http/Controllers/ItemController.php:240
* @route '/items/{item}'
*/
destroy.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ItemController::destroy
* @see app/Http/Controllers/ItemController.php:240
* @route '/items/{item}'
*/
destroy.delete = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\ItemController::relatedItemTargets
* @see app/Http/Controllers/ItemController.php:173
* @route '/items/{item}/related-item-targets'
*/
export const relatedItemTargets = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: relatedItemTargets.url(args, options),
    method: 'get',
})

relatedItemTargets.definition = {
    methods: ["get","head"],
    url: '/items/{item}/related-item-targets',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ItemController::relatedItemTargets
* @see app/Http/Controllers/ItemController.php:173
* @route '/items/{item}/related-item-targets'
*/
relatedItemTargets.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return relatedItemTargets.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ItemController::relatedItemTargets
* @see app/Http/Controllers/ItemController.php:173
* @route '/items/{item}/related-item-targets'
*/
relatedItemTargets.get = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: relatedItemTargets.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ItemController::relatedItemTargets
* @see app/Http/Controllers/ItemController.php:173
* @route '/items/{item}/related-item-targets'
*/
relatedItemTargets.head = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: relatedItemTargets.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\ImageSearchController::imageSearch
* @see app/Http/Controllers/ImageSearchController.php:34
* @route '/items/{item}/image-search'
*/
export const imageSearch = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: imageSearch.url(args, options),
    method: 'get',
})

imageSearch.definition = {
    methods: ["get","head"],
    url: '/items/{item}/image-search',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\ImageSearchController::imageSearch
* @see app/Http/Controllers/ImageSearchController.php:34
* @route '/items/{item}/image-search'
*/
imageSearch.url = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
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

    return imageSearch.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\ImageSearchController::imageSearch
* @see app/Http/Controllers/ImageSearchController.php:34
* @route '/items/{item}/image-search'
*/
imageSearch.get = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: imageSearch.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\ImageSearchController::imageSearch
* @see app/Http/Controllers/ImageSearchController.php:34
* @route '/items/{item}/image-search'
*/
imageSearch.head = (args: { item: number | { id: number } } | [item: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: imageSearch.url(args, options),
    method: 'head',
})

const items = {
    moveTargets: Object.assign(moveTargets, moveTargets),
    move: Object.assign(move, move),
    analyzePhoto: Object.assign(analyzePhoto, analyzePhoto),
    index: Object.assign(index, index),
    create: Object.assign(create, create),
    store: Object.assign(store, store),
    show: Object.assign(show, show),
    edit: Object.assign(edit, edit),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
    relatedItemTargets: Object.assign(relatedItemTargets, relatedItemTargets),
    imageSearch: Object.assign(imageSearch, imageSearch),
    images: Object.assign(images, images),
    box: Object.assign(box, box),
    relatedItems: Object.assign(relatedItems, relatedItems),
}

export default items