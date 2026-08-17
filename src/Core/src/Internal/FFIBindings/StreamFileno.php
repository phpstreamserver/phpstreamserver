<?php

declare(strict_types=1);

namespace PHPStreamServer\Core\Internal\FFIBindings;

use FFI\CData;
use Revolt\EventLoop;

/**
 * Extracts the underlying OS file descriptor number from a PHP stream resource by interfacing with internal Zend Engine and PHP Stream C APIs via FFI
 *
 * @psalm-suppress UndefinedPropertyFetch, UndefinedPropertyAssignment, InvalidArgument, InvalidPassByReference, TypeDoesNotContainType, NoValue, MixedPropertyFetch, MixedArgument
 */
final class StreamFileno
{
    private const PHP_STREAM_AS_FD_FOR_SELECT = 3;
    private const PHP_STREAM_AS_FD = 1;
    private const ZTYPE_RESOURCE = 9;

    // https://github.com/chopins/ffi-ext/blob/master/src/php.h
    private const CDEF = <<<'CDEF'
        typedef unsigned char zend_uchar;

        typedef struct _zend_refcounted_h {
            uint32_t refcount;
            union {
                uint32_t type_info;
            } u;
        } zend_refcounted_h;
        
        typedef struct _zval_struct zval;
        typedef struct _zend_resource zend_resource;
        typedef struct _php_stream php_stream;
        typedef struct _zend_array HashTable;
        typedef void (*dtor_func_t)(zval *pDest);
        
        struct _zend_resource {
            zend_refcounted_h gc;
            int               handle;
            int               type;
            void             *ptr;
        };
        
        struct _zval_struct {
            union {
                zend_long      lval;
                double         dval;
                void          *counted;
                void          *str;
                HashTable     *arr;
                void          *obj;
                zend_resource *res;
                void          *ref;
                void          *ast;
                zval          *zv;
                void          *ptr;
                void          *ce;
                void          *func;
                struct {
                    uint32_t w1;
                    uint32_t w2;
                } ww;
            } value;
            union {
                struct {
                    zend_uchar type;
                    zend_uchar type_flags;
                    zend_uchar const_flags;
                    zend_uchar reserved;
                } v;
                uint32_t type_info;
            } u1;
            union {
                uint32_t var_flags;
                uint32_t next;
                uint32_t cache_slot;
                uint32_t lineno;
                uint32_t num_args;
                uint32_t fe_pos;
                uint32_t fe_iter_idx;
            } u2;
        };
        
        typedef struct _Bucket {
            zval       val;
            zend_ulong h;
            void      *key;
        } Bucket;
        
        struct _zend_array {
            zend_refcounted_h gc;
            union {
                struct {
                    zend_uchar flags;
                    zend_uchar _unused;
                    zend_uchar nIteratorsCount;
                    zend_uchar _unused2;
                } v;
                uint32_t flags;
            } u;
            uint32_t    nTableMask;
            Bucket     *arData;
            uint32_t    nNumUsed;
            uint32_t    nNumOfElements;
            uint32_t    nTableSize;
            uint32_t    nInternalPointer;
            zend_long   nNextFreeElement;
            dtor_func_t pDestructor;
        };
        
        HashTable *zend_rebuild_symbol_table(void);
        HashTable *zend_array_dup(HashTable *source);
        void zend_array_destroy(HashTable *ht);
        void *zend_fetch_resource2(zend_resource *res, const char *resource_type_name, int resource_type1, int resource_type2);
        int php_file_le_stream(void);
        int php_file_le_pstream(void);
        int _php_stream_cast(php_stream *stream, int castas, void **ret, int show_err);
    CDEF;

    /**
     * @param resource $resource
     */
    public static function get(mixed $resource): int|null
    {
        $zval = self::zval($resource);
        if (!self::isResource($zval)) {
            return null;
        }

        $zresource = $zval->value->res;
        $phpStream = self::ffi()->zend_fetch_resource2($zresource, 'stream', self::ffi()->php_file_le_stream(), self::ffi()->php_file_le_pstream());

        if (self::isNull($phpStream)) {
            return null;
        }

        $filenoCData = self::ffi()->new('int');
        $filenoCData->cdata = -1;
        $castTypeList = [self::PHP_STREAM_AS_FD_FOR_SELECT, self::PHP_STREAM_AS_FD];
        foreach ($castTypeList as $castType) {
            self::ffi()->_php_stream_cast($phpStream, $castType, self::ffi()->cast('void *', \FFI::addr($filenoCData)), 0);
            if ($filenoCData->cdata !== -1) {
                return $filenoCData->cdata;
            }
        }

        return null;
    }

    private static function ffi(): \FFI
    {
        static $map;
        $map ??= new \WeakMap();

        return $map[EventLoop::getDriver()] ??= (static function (): \FFI {
            $bitSize = PHP_INT_SIZE * 8;
            $header = "typedef int{$bitSize}_t zend_long;typedef uint{$bitSize}_t zend_ulong;typedef int{$bitSize}_t zend_off_t;";
            $header .= self::CDEF;
            return \FFI::cdef($header);
        })();
    }

    private static function zval(mixed $var): CData
    {
        $symbolTable = self::ffi()->zend_rebuild_symbol_table();
        $symbolHashTable = self::ffi()->zend_array_dup($symbolTable);
        $zval = $symbolHashTable->arData->val;
        self::ffi()->zend_array_destroy($symbolHashTable);

        return $zval;
    }

    private static function isResource(CData $zval): bool
    {
        return $zval->u1->v->type === self::ZTYPE_RESOURCE;
    }

    private static function isNull(CData|null $zval): bool
    {
        return $zval === null || \FFI::isNull($zval);
    }
}
