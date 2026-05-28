import { queryParams, type RouteQueryOptions, type RouteDefinition } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Household\BackupController::index
* @see app/Http/Controllers/Household/BackupController.php:27
* @route '/household/backup'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/household/backup',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Household\BackupController::index
* @see app/Http/Controllers/Household/BackupController.php:27
* @route '/household/backup'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\BackupController::index
* @see app/Http/Controllers/Household/BackupController.php:27
* @route '/household/backup'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Household\BackupController::index
* @see app/Http/Controllers/Household/BackupController.php:27
* @route '/household/backup'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Household\BackupController::exportMethod
* @see app/Http/Controllers/Household/BackupController.php:34
* @route '/household/backup/export'
*/
export const exportMethod = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(options),
    method: 'get',
})

exportMethod.definition = {
    methods: ["get","head"],
    url: '/household/backup/export',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Household\BackupController::exportMethod
* @see app/Http/Controllers/Household/BackupController.php:34
* @route '/household/backup/export'
*/
exportMethod.url = (options?: RouteQueryOptions) => {
    return exportMethod.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\BackupController::exportMethod
* @see app/Http/Controllers/Household/BackupController.php:34
* @route '/household/backup/export'
*/
exportMethod.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: exportMethod.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Household\BackupController::exportMethod
* @see app/Http/Controllers/Household/BackupController.php:34
* @route '/household/backup/export'
*/
exportMethod.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: exportMethod.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Household\BackupController::importMethod
* @see app/Http/Controllers/Household/BackupController.php:44
* @route '/household/backup/import'
*/
export const importMethod = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importMethod.url(options),
    method: 'post',
})

importMethod.definition = {
    methods: ["post"],
    url: '/household/backup/import',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Household\BackupController::importMethod
* @see app/Http/Controllers/Household/BackupController.php:44
* @route '/household/backup/import'
*/
importMethod.url = (options?: RouteQueryOptions) => {
    return importMethod.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Household\BackupController::importMethod
* @see app/Http/Controllers/Household/BackupController.php:44
* @route '/household/backup/import'
*/
importMethod.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: importMethod.url(options),
    method: 'post',
})

const BackupController = { index, exportMethod, importMethod, export: exportMethod, import: importMethod }

export default BackupController