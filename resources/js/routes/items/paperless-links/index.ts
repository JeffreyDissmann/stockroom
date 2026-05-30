import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Items\PaperlessLinkController::destroy
* @see app/Http/Controllers/Items/PaperlessLinkController.php:22
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
* @see app/Http/Controllers/Items/PaperlessLinkController.php:22
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
* @see app/Http/Controllers/Items/PaperlessLinkController.php:22
* @route '/items/{item}/paperless-links/{document}'
*/
destroy.delete = (args: { item: number | { id: number }, document: string | number } | [item: number | { id: number }, document: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const paperlessLinks = {
    destroy: Object.assign(destroy, destroy),
}

export default paperlessLinks