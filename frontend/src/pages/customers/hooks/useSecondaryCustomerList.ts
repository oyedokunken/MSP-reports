import { useBaseCustomerList } from './useBaseCustomerList'

export const useSecondaryCustomerList = () => {
    return useBaseCustomerList(false)
}
