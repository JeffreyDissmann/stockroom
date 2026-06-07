import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Items\PaperlessFieldSuggestionController::__invoke
* @see app/Http/Controllers/Items/PaperlessFieldSuggestionController.php:34
* @route '/items/{item}/paperless-links/{document}/suggest-fields'
*/
const PaperlessFieldSuggestionController = (args: { item: number | { id: number }, document: string | number } | [item: number | { id: number }, document: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: PaperlessFieldSuggestionController.url(args, options),
    method: 'post',
})

PaperlessFieldSuggestionController.definition = {
    methods: ["post"],
    url: '/items/{item}/paperless-links/{document}/suggest-fields',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Items\PaperlessFieldSuggestionController::__invoke
* @see app/Http/Controllers/Items/PaperlessFieldSuggestionController.php:34
* @route '/items/{item}/paperless-links/{document}/suggest-fields'
*/
PaperlessFieldSuggestionController.url = (args: { item: number | { id: number }, document: string | number } | [item: number | { id: number }, document: string | number ], options?: RouteQueryOptions) => {
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

    return PaperlessFieldSuggestionController.definition.url
            .replace('{item}', parsedArgs.item.toString())
            .replace('{document}', parsedArgs.document.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Items\PaperlessFieldSuggestionController::__invoke
* @see app/Http/Controllers/Items/PaperlessFieldSuggestionController.php:34
* @route '/items/{item}/paperless-links/{document}/suggest-fields'
*/
PaperlessFieldSuggestionController.post = (args: { item: number | { id: number }, document: string | number } | [item: number | { id: number }, document: string | number ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: PaperlessFieldSuggestionController.url(args, options),
    method: 'post',
})

export default PaperlessFieldSuggestionController