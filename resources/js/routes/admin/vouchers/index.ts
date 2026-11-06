import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
import bulk36930f from './bulk'
/**
* @see \App\Http\Controllers\Admin\VoucherController::index
 * @see app/Http/Controllers/Admin/VoucherController.php:37
 * @route '/admin/vouchers'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/admin/vouchers',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\VoucherController::index
 * @see app/Http/Controllers/Admin/VoucherController.php:37
 * @route '/admin/vouchers'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\VoucherController::index
 * @see app/Http/Controllers/Admin/VoucherController.php:37
 * @route '/admin/vouchers'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\VoucherController::index
 * @see app/Http/Controllers/Admin/VoucherController.php:37
 * @route '/admin/vouchers'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\VoucherController::index
 * @see app/Http/Controllers/Admin/VoucherController.php:37
 * @route '/admin/vouchers'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\VoucherController::index
 * @see app/Http/Controllers/Admin/VoucherController.php:37
 * @route '/admin/vouchers'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\VoucherController::index
 * @see app/Http/Controllers/Admin/VoucherController.php:37
 * @route '/admin/vouchers'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\Admin\VoucherController::create
 * @see app/Http/Controllers/Admin/VoucherController.php:100
 * @route '/admin/vouchers/create'
 */
export const create = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})

create.definition = {
    methods: ["get","head"],
    url: '/admin/vouchers/create',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\VoucherController::create
 * @see app/Http/Controllers/Admin/VoucherController.php:100
 * @route '/admin/vouchers/create'
 */
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\VoucherController::create
 * @see app/Http/Controllers/Admin/VoucherController.php:100
 * @route '/admin/vouchers/create'
 */
create.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: create.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\VoucherController::create
 * @see app/Http/Controllers/Admin/VoucherController.php:100
 * @route '/admin/vouchers/create'
 */
create.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: create.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\VoucherController::create
 * @see app/Http/Controllers/Admin/VoucherController.php:100
 * @route '/admin/vouchers/create'
 */
    const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: create.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\VoucherController::create
 * @see app/Http/Controllers/Admin/VoucherController.php:100
 * @route '/admin/vouchers/create'
 */
        createForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\VoucherController::create
 * @see app/Http/Controllers/Admin/VoucherController.php:100
 * @route '/admin/vouchers/create'
 */
        createForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: create.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    create.form = createForm
/**
* @see \App\Http\Controllers\Admin\VoucherController::check
 * @see app/Http/Controllers/Admin/VoucherController.php:129
 * @route '/admin/vouchers/check'
 */
export const check = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: check.url(options),
    method: 'post',
})

check.definition = {
    methods: ["post"],
    url: '/admin/vouchers/check',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\VoucherController::check
 * @see app/Http/Controllers/Admin/VoucherController.php:129
 * @route '/admin/vouchers/check'
 */
check.url = (options?: RouteQueryOptions) => {
    return check.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\VoucherController::check
 * @see app/Http/Controllers/Admin/VoucherController.php:129
 * @route '/admin/vouchers/check'
 */
check.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: check.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\VoucherController::check
 * @see app/Http/Controllers/Admin/VoucherController.php:129
 * @route '/admin/vouchers/check'
 */
    const checkForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: check.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\VoucherController::check
 * @see app/Http/Controllers/Admin/VoucherController.php:129
 * @route '/admin/vouchers/check'
 */
        checkForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: check.url(options),
            method: 'post',
        })
    
    check.form = checkForm
/**
* @see \App\Http\Controllers\Admin\VoucherController::store
 * @see app/Http/Controllers/Admin/VoucherController.php:163
 * @route '/admin/vouchers'
 */
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/admin/vouchers',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\VoucherController::store
 * @see app/Http/Controllers/Admin/VoucherController.php:163
 * @route '/admin/vouchers'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\VoucherController::store
 * @see app/Http/Controllers/Admin/VoucherController.php:163
 * @route '/admin/vouchers'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\VoucherController::store
 * @see app/Http/Controllers/Admin/VoucherController.php:163
 * @route '/admin/vouchers'
 */
    const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\VoucherController::store
 * @see app/Http/Controllers/Admin/VoucherController.php:163
 * @route '/admin/vouchers'
 */
        storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Admin\VoucherController::update
 * @see app/Http/Controllers/Admin/VoucherController.php:203
 * @route '/admin/vouchers/{voucher}'
 */
export const update = (args: { voucher: number | { id: number } } | [voucher: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/admin/vouchers/{voucher}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\Admin\VoucherController::update
 * @see app/Http/Controllers/Admin/VoucherController.php:203
 * @route '/admin/vouchers/{voucher}'
 */
update.url = (args: { voucher: number | { id: number } } | [voucher: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { voucher: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { voucher: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    voucher: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        voucher: typeof args.voucher === 'object'
                ? args.voucher.id
                : args.voucher,
                }

    return update.definition.url
            .replace('{voucher}', parsedArgs.voucher.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\VoucherController::update
 * @see app/Http/Controllers/Admin/VoucherController.php:203
 * @route '/admin/vouchers/{voucher}'
 */
update.put = (args: { voucher: number | { id: number } } | [voucher: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

    /**
* @see \App\Http\Controllers\Admin\VoucherController::update
 * @see app/Http/Controllers/Admin/VoucherController.php:203
 * @route '/admin/vouchers/{voucher}'
 */
    const updateForm = (args: { voucher: number | { id: number } } | [voucher: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PUT',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\VoucherController::update
 * @see app/Http/Controllers/Admin/VoucherController.php:203
 * @route '/admin/vouchers/{voucher}'
 */
        updateForm.put = (args: { voucher: number | { id: number } } | [voucher: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PUT',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\Admin\VoucherController::destroy
 * @see app/Http/Controllers/Admin/VoucherController.php:259
 * @route '/admin/vouchers/{voucher}'
 */
export const destroy = (args: { voucher: number | { id: number } } | [voucher: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/admin/vouchers/{voucher}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Admin\VoucherController::destroy
 * @see app/Http/Controllers/Admin/VoucherController.php:259
 * @route '/admin/vouchers/{voucher}'
 */
destroy.url = (args: { voucher: number | { id: number } } | [voucher: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { voucher: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { voucher: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    voucher: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        voucher: typeof args.voucher === 'object'
                ? args.voucher.id
                : args.voucher,
                }

    return destroy.definition.url
            .replace('{voucher}', parsedArgs.voucher.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\VoucherController::destroy
 * @see app/Http/Controllers/Admin/VoucherController.php:259
 * @route '/admin/vouchers/{voucher}'
 */
destroy.delete = (args: { voucher: number | { id: number } } | [voucher: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Admin\VoucherController::destroy
 * @see app/Http/Controllers/Admin/VoucherController.php:259
 * @route '/admin/vouchers/{voucher}'
 */
    const destroyForm = (args: { voucher: number | { id: number } } | [voucher: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\VoucherController::destroy
 * @see app/Http/Controllers/Admin/VoucherController.php:259
 * @route '/admin/vouchers/{voucher}'
 */
        destroyForm.delete = (args: { voucher: number | { id: number } } | [voucher: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
/**
* @see \App\Http\Controllers\Admin\VoucherController::bulk
 * @see app/Http/Controllers/Admin/VoucherController.php:275
 * @route '/admin/vouchers/bulk'
 */
export const bulk = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: bulk.url(options),
    method: 'get',
})

bulk.definition = {
    methods: ["get","head"],
    url: '/admin/vouchers/bulk',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\VoucherController::bulk
 * @see app/Http/Controllers/Admin/VoucherController.php:275
 * @route '/admin/vouchers/bulk'
 */
bulk.url = (options?: RouteQueryOptions) => {
    return bulk.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\VoucherController::bulk
 * @see app/Http/Controllers/Admin/VoucherController.php:275
 * @route '/admin/vouchers/bulk'
 */
bulk.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: bulk.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\VoucherController::bulk
 * @see app/Http/Controllers/Admin/VoucherController.php:275
 * @route '/admin/vouchers/bulk'
 */
bulk.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: bulk.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\VoucherController::bulk
 * @see app/Http/Controllers/Admin/VoucherController.php:275
 * @route '/admin/vouchers/bulk'
 */
    const bulkForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: bulk.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\VoucherController::bulk
 * @see app/Http/Controllers/Admin/VoucherController.php:275
 * @route '/admin/vouchers/bulk'
 */
        bulkForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: bulk.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\VoucherController::bulk
 * @see app/Http/Controllers/Admin/VoucherController.php:275
 * @route '/admin/vouchers/bulk'
 */
        bulkForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: bulk.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    bulk.form = bulkForm
/**
* @see \App\Http\Controllers\Admin\VoucherController::batchStatus
 * @see app/Http/Controllers/Admin/VoucherController.php:478
 * @route '/admin/vouchers/batch/{batchId}'
 */
export const batchStatus = (args: { batchId: string | number } | [batchId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: batchStatus.url(args, options),
    method: 'get',
})

batchStatus.definition = {
    methods: ["get","head"],
    url: '/admin/vouchers/batch/{batchId}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\VoucherController::batchStatus
 * @see app/Http/Controllers/Admin/VoucherController.php:478
 * @route '/admin/vouchers/batch/{batchId}'
 */
batchStatus.url = (args: { batchId: string | number } | [batchId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { batchId: args }
    }

    
    if (Array.isArray(args)) {
        args = {
                    batchId: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        batchId: args.batchId,
                }

    return batchStatus.definition.url
            .replace('{batchId}', parsedArgs.batchId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\VoucherController::batchStatus
 * @see app/Http/Controllers/Admin/VoucherController.php:478
 * @route '/admin/vouchers/batch/{batchId}'
 */
batchStatus.get = (args: { batchId: string | number } | [batchId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: batchStatus.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\VoucherController::batchStatus
 * @see app/Http/Controllers/Admin/VoucherController.php:478
 * @route '/admin/vouchers/batch/{batchId}'
 */
batchStatus.head = (args: { batchId: string | number } | [batchId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: batchStatus.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\VoucherController::batchStatus
 * @see app/Http/Controllers/Admin/VoucherController.php:478
 * @route '/admin/vouchers/batch/{batchId}'
 */
    const batchStatusForm = (args: { batchId: string | number } | [batchId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: batchStatus.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\VoucherController::batchStatus
 * @see app/Http/Controllers/Admin/VoucherController.php:478
 * @route '/admin/vouchers/batch/{batchId}'
 */
        batchStatusForm.get = (args: { batchId: string | number } | [batchId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: batchStatus.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\VoucherController::batchStatus
 * @see app/Http/Controllers/Admin/VoucherController.php:478
 * @route '/admin/vouchers/batch/{batchId}'
 */
        batchStatusForm.head = (args: { batchId: string | number } | [batchId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: batchStatus.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    batchStatus.form = batchStatusForm
/**
* @see \App\Http\Controllers\Admin\VoucherController::latestBatch
 * @see app/Http/Controllers/Admin/VoucherController.php:495
 * @route '/admin/vouchers/latest-batch'
 */
export const latestBatch = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: latestBatch.url(options),
    method: 'get',
})

latestBatch.definition = {
    methods: ["get","head"],
    url: '/admin/vouchers/latest-batch',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Admin\VoucherController::latestBatch
 * @see app/Http/Controllers/Admin/VoucherController.php:495
 * @route '/admin/vouchers/latest-batch'
 */
latestBatch.url = (options?: RouteQueryOptions) => {
    return latestBatch.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\VoucherController::latestBatch
 * @see app/Http/Controllers/Admin/VoucherController.php:495
 * @route '/admin/vouchers/latest-batch'
 */
latestBatch.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: latestBatch.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\Admin\VoucherController::latestBatch
 * @see app/Http/Controllers/Admin/VoucherController.php:495
 * @route '/admin/vouchers/latest-batch'
 */
latestBatch.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: latestBatch.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\Admin\VoucherController::latestBatch
 * @see app/Http/Controllers/Admin/VoucherController.php:495
 * @route '/admin/vouchers/latest-batch'
 */
    const latestBatchForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: latestBatch.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\Admin\VoucherController::latestBatch
 * @see app/Http/Controllers/Admin/VoucherController.php:495
 * @route '/admin/vouchers/latest-batch'
 */
        latestBatchForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: latestBatch.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\Admin\VoucherController::latestBatch
 * @see app/Http/Controllers/Admin/VoucherController.php:495
 * @route '/admin/vouchers/latest-batch'
 */
        latestBatchForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: latestBatch.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    latestBatch.form = latestBatchForm
/**
* @see \App\Http\Controllers\Admin\VoucherOcrController::scanPhoto
 * @see app/Http/Controllers/Admin/VoucherOcrController.php:21
 * @route '/admin/vouchers/scan-photo'
 */
export const scanPhoto = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: scanPhoto.url(options),
    method: 'post',
})

scanPhoto.definition = {
    methods: ["post"],
    url: '/admin/vouchers/scan-photo',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Admin\VoucherOcrController::scanPhoto
 * @see app/Http/Controllers/Admin/VoucherOcrController.php:21
 * @route '/admin/vouchers/scan-photo'
 */
scanPhoto.url = (options?: RouteQueryOptions) => {
    return scanPhoto.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Admin\VoucherOcrController::scanPhoto
 * @see app/Http/Controllers/Admin/VoucherOcrController.php:21
 * @route '/admin/vouchers/scan-photo'
 */
scanPhoto.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: scanPhoto.url(options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Admin\VoucherOcrController::scanPhoto
 * @see app/Http/Controllers/Admin/VoucherOcrController.php:21
 * @route '/admin/vouchers/scan-photo'
 */
    const scanPhotoForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: scanPhoto.url(options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Admin\VoucherOcrController::scanPhoto
 * @see app/Http/Controllers/Admin/VoucherOcrController.php:21
 * @route '/admin/vouchers/scan-photo'
 */
        scanPhotoForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: scanPhoto.url(options),
            method: 'post',
        })
    
    scanPhoto.form = scanPhotoForm
const vouchers = {
    index: Object.assign(index, index),
create: Object.assign(create, create),
check: Object.assign(check, check),
store: Object.assign(store, store),
update: Object.assign(update, update),
destroy: Object.assign(destroy, destroy),
bulk: Object.assign(bulk, bulk36930f),
batchStatus: Object.assign(batchStatus, batchStatus),
latestBatch: Object.assign(latestBatch, latestBatch),
scanPhoto: Object.assign(scanPhoto, scanPhoto),
}

export default vouchers