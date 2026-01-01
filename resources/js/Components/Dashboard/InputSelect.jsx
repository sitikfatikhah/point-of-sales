import React, { useState, useMemo } from 'react'
import { Listbox } from '@headlessui/react'
import { IconChevronDown, IconCircle, IconCircleFilled } from '@tabler/icons-react'

export default function InputSelect({ selected, data = [], setSelected, label, errors, placeholder, multiple = false, searchable = false, displayKey = 'name', getOptionLabel, getOptionValue, }) {
    const [search, setSearch] = useState('')

    const items = Array.isArray(data) ? data : []

    const resolveLabel = (item) => {
        if (getOptionLabel) return getOptionLabel(item)
        if (typeof item !== 'object') return String(item)
        return item?.[displayKey] ?? ''
    }

    const resolveValue = (item) => {
        if (getOptionValue) return getOptionValue(item)
        if (typeof item !== 'object') return item
        return item?.id ?? item?.value
    }

    const filteredData = useMemo(() => {
        if (!searchable || !search) return items

        return items.filter(item =>
            String(item?.[displayKey] ?? '')
                .toLowerCase()
                .includes(search.toLowerCase())
        )
    }, [items, search, searchable, displayKey])

    const isSelected = (item) => {
        const val = resolveValue(item)

        if (multiple) {
            return Array.isArray(selected)
                ? selected.some(
                      (s) => resolveValue(s) === val
                  )
                : false
        }

        return selected
            ? resolveValue(selected) === val
            : false
    }

     const displaySelected = () => {
        if (multiple) {
            if (!Array.isArray(selected) || selected.length === 0)
                return placeholder

            return selected.map(resolveLabel).join(', ')
        }

        return selected ? resolveLabel(selected) : placeholder
    }



    // const filteredData = data.filter(item =>
    //     item[displayKey]?.toLowerCase().includes(search.toLowerCase())
    // )

    return (
        <div className='flex flex-col gap-2 relative'>
            <label className='text-gray-600 text-sm'>{label}</label>
            <Listbox value={selected} onChange={setSelected} multiple={multiple} by="id">
                <Listbox.Button className={'w-full px-3 py-1.5 border text-sm rounded-md focus:outline-none focus:ring-0 flex justify-between items-center gap-8 bg-white text-gray-700 focus:border-gray-200 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-gray-700 dark:border-gray-800'}>
                    <span className="truncate">
                        {displaySelected()}
                    </span>
                    {/* {multiple ? (
                        selected.length > 0 ? selected.map(item => item[displayKey]).join(', ') : placeholder
                    ) : (
                        selected ? selected[displayKey] : placeholder
                    )} */}
                    <IconChevronDown size={20} strokeWidth={1.5} />
                </Listbox.Button>
                <Listbox.Options className={'absolute w-full z-20 p-4 border rounded-lg flex flex-col gap-2 bg-gray-100 dark:border-gray-900 dark:bg-gray-950'}>
                    {searchable && (
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search..."
                            className="w-full px-3 py-1.5 mb-2 text-sm border rounded-md bg-white text-gray-700 border-gray-200 focus:outline-none focus:border-gray-300 dark:bg-gray-900 dark:text-gray-300 dark:border-gray-800 dark:focus:border-gray-700"
                        />
                    )}
                    {filteredData.map((item) => (
                        <Listbox.Option
                            key={resolveValue(item) ?? idx}
                            value={item}
                        >
                            {() => (
                                <div className="flex items-center gap-2 px-3 py-1.5 text-sm cursor-pointer rounded-md bg-white hover:bg-gray-200 dark:bg-gray-900 dark:hover:bg-gray-800">
                                    {isSelected(item) ? (
                                        <IconCircleFilled
                                            size={14}
                                            className="text-teal-500"
                                        />
                                    ) : (
                                        <IconCircle size={14} />
                                    )}
                                    <span className="truncate">
                                        {resolveLabel(item)}
                                    </span>
                                </div>
                            )}
                        </Listbox.Option>
                    ))}
                </Listbox.Options>
            </Listbox>
            {errors && (
                <small className='text-xs text-red-500'>{errors}</small>
            )}
        </div>
    )
}
