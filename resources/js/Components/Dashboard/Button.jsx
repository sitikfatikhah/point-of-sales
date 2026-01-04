import { Link } from '@inertiajs/react'
import React from 'react'
import { useForm } from '@inertiajs/react';
import Swal from 'sweetalert2'

export default function Button({ className, icon, label, type, href, added, url, id, variant = 'primary', size = 'md', ...props }) {

    const { delete: destroy } = useForm()

    const deleteData = (url) => {
        Swal.fire({
            title: 'Apakah kamu yakin?',
            text: 'Data yang dihapus tidak dapat dikembalikan!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#ef4444',
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Batal',
        }).then(({ isConfirmed }) => {
            if (!isConfirmed) return

            destroy(url, {
                preserveScroll: true,

                onSuccess: () => {
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Data berhasil dihapus!',
                        icon: 'success',
                        showConfirmButton: false,
                        timer: 1500,
                        customClass: {
                            popup: 'dark:bg-slate-900 dark:text-slate-100',
                        },
                    })
                },

                onError: (errors) => {
                    Swal.fire({
                        title: 'Gagal!',
                        text: errors?.error ?? 'Data tidak bisa dihapus.',
                        icon: 'error',
                        // confirmButtonText: 'OK',
                        customClass: {
                            popup: 'dark:bg-slate-900 dark:text-slate-100',
                        },
                    })
                },
            })
        })
    }

    // Base styles
    const baseStyles = 'inline-flex items-center justify-center gap-2 font-medium rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-slate-900 disabled:opacity-50 disabled:cursor-not-allowed'

    // Size variants
    const sizes = {
        xs: 'px-2.5 py-1.5 text-xs',
        sm: 'px-3 py-2 text-sm',
        md: 'px-4 py-2.5 text-sm',
        lg: 'px-5 py-3 text-base',
    }

    // Variant styles
    const variants = {
        primary: 'bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500 shadow-sm',
        secondary: 'bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 focus:ring-slate-500',
        success: 'bg-emerald-600 hover:bg-emerald-700 text-white focus:ring-emerald-500 shadow-sm',
        danger: 'bg-red-600 hover:bg-red-700 text-white focus:ring-red-500 shadow-sm',
        warning: 'bg-amber-500 hover:bg-amber-600 text-white focus:ring-amber-500 shadow-sm',
        ghost: 'hover:bg-slate-100 text-slate-600 dark:hover:bg-slate-800 dark:text-slate-400 focus:ring-slate-500',
        outline: 'border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 focus:ring-slate-500',
    }

    const buttonClass = `${baseStyles} ${sizes[size]} ${variants[variant] || ''} ${className || ''}`

    return (
        <>
            {type === 'link' &&
                <Link href={href} className={buttonClass}>
                    {icon} {label && <span className={`${added === true ? 'hidden lg:block' : ''}`}>{label}</span>}
                </Link>
            }
            {type === 'button' &&
                <button className={buttonClass} {...props}>
                    {icon} {label && <span className={`${added === true ? 'hidden md:block' : ''}`}>{label}</span>}
                </button>
            }
            {type === 'submit' &&
                <button type='submit' className={buttonClass} {...props}>
                    {icon} {label && <span className={`${added === true ? 'hidden lg:block' : ''}`}>{label}</span>}
                </button>
            }
            {type === 'delete' &&
                <button onClick={() => deleteData(url)} className={`${baseStyles} ${sizes.sm} ${variants.danger} ${className || ''}`} {...props}>
                    {icon} {label && <span className='hidden sm:block'>{label}</span>}
                </button>
            }
            {type === 'modal' &&
                <button className={buttonClass} {...props}>
                    {icon} {label && <span>{label}</span>}
                </button>
            }
            {type === 'edit' &&
                <Link href={href} className={`${baseStyles} ${sizes.sm} ${variants.warning} ${className || ''}`} {...props}>
                    {icon} {label && <span className='hidden sm:block'>{label}</span>}
                </Link>
            }
            {type === 'bulk' &&
                <button {...props} className={buttonClass}>
                    {icon} {label && <span className={`${added === true ? 'hidden lg:block' : ''}`}>{label}</span>}
                </button>
            }
        </>
    )
}
